<?php

namespace blt950\OauthGeneric\Controllers;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Command\StartDiscussion;
use Flarum\Http\RequestUtil;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Laminas\Diactoros\Response\JsonResponse;

class CreateProjectController extends AbstractCreateController
{
    public $serializer = DiscussionSerializer::class;

    protected $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $data = $request->getParsedBody();

        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $raw = (string) $request->getBody();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function truncateTitle(string $title, int $max = 80): string
    {
        if ($title === '') return $title;
        if (function_exists('mb_substr')) {
            return mb_substr($title, 0, $max);
        }
        return substr($title, 0, $max);
    }

    private function startDiscussionCompat(User $actor, array $payload, ServerRequestInterface $request): Discussion
    {
        // Flarum core changed StartDiscussion constructor between versions (2 args -> 3 args with IP).
        $ref = new \ReflectionClass(StartDiscussion::class);
        $ctor = $ref->getConstructor();
        $required = $ctor ? $ctor->getNumberOfRequiredParameters() : 2;

        if ($required >= 3) {
            $ipAddress = Arr::get($request->getServerParams(), 'REMOTE_ADDR');
            return $this->bus->dispatch(new StartDiscussion($actor, $payload, $ipAddress));
        }

        return $this->bus->dispatch(new StartDiscussion($actor, $payload));
    }

    private function tagsRelationship(array $tagIds): array
    {
        $data = [];
        foreach ($tagIds as $id) {
            $data[] = ['type' => 'tags', 'id' => (string) $id];
        }
        return ['tags' => ['data' => $data]];
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $data = $this->parseBody($request);

        // Проверяем права доступа (только service user может создавать от лица других)
        if (!$actor->hasPermission('discussion.startWithoutApproval')) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }

        try {
            // Валидация данных
            $requiredFields = ['project_title', 'user_email', 'tag_slug'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new \Flarum\Foundation\ValidationException([
                        $field => "Field {$field} is required"
                    ]);
                }
            }

            // Находим пользователя UNRIP в Flarum
            $flarumUser = User::where('email', $data['user_email'])->first();
            
            if (!$flarumUser) {
                throw new \Flarum\Foundation\ValidationException([
                    'user_email' => 'User not found in Flarum'
                ]);
            }

            // Находим тег проекта
            $tag = Tag::where('slug', $data['tag_slug'])->first();
            if (!$tag) {
                throw new \Flarum\Foundation\ValidationException([
                    'tag_slug' => 'Tag not found'
                ]);
            }

            $title = $this->truncateTitle((string) $data['project_title'], 80);
            $content = isset($data['project_description']) && $data['project_description'] !== null
                ? (string) $data['project_description']
                : 'Project discussion created from UNRIP';

            $tagIds = [$tag->id];
            // Support multiple Flarum core versions:
            // - some expect data in JSON:API-ish shape: attributes.title/content + relationships.tags
            // - older forks may accept title/content/tags at top-level
            $payload = [
                'title' => $title,
                'content' => $content,
                'tags' => $tagIds,
                'attributes' => [
                    'title' => $title,
                    'content' => $content,
                ],
                'relationships' => $this->tagsRelationship($tagIds),
            ];

            // Создаем дискуссию от лица service user (actor), чтобы не зависеть от прав целевого пользователя.
            // Затем перепривязываем автора дискуссии/первого поста на нужного пользователя.
            $discussion = $this->startDiscussionCompat($actor, $payload, $request);

            try {
                $discussion->user_id = $flarumUser->id;
                $discussion->save();

                // Также меняем автора первого поста (иначе в UI может остаться service user).
                $firstPost = $discussion->firstPost;
                if ($firstPost) {
                    $firstPost->user_id = $flarumUser->id;
                    $firstPost->save();
                }
            } catch (\Throwable $e) {
                \error_log('[unrip.create-project][reassign] ' . $e->getMessage());
            }

            return $discussion;
        } catch (\Throwable $e) {
            \error_log('[unrip.create-project] ' . $e->getMessage());
            \error_log('[unrip.create-project] ' . $e->getTraceAsString());

            $debug = \getenv('UNRIP_API_DEBUG') === '1';
            $public = $debug ? $e->getMessage() : 'Internal error (see Flarum logs)';
            throw new \Flarum\Foundation\ValidationException([
                'server' => $public
            ]);
        }
    }
}