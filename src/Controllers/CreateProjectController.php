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

            // Создаем дискуссию от лица пользователя
            $discussion = $this->bus->dispatch(
                new StartDiscussion($flarumUser, [
                    'title' => $title,
                    'content' => $content,
                    'tags' => [$tag->id]
                ])
            );

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