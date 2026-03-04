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

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $data = $request->getParsedBody();

        // Проверяем права доступа (только service user может создавать от лица других)
        if (!$actor->hasPermission('discussion.startWithoutApproval')) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }

        // Валидация данных
        $requiredFields = ['project_title', 'user_email', 'tag_slug'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
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

        // Создаем дискуссию от лица пользователя
        $discussion = $this->bus->dispatch(
            new StartDiscussion($flarumUser, [
                'title' => $data['project_title'],
                'content' => $data['project_description'] ?? 'Project discussion created from UNRIP',
                'tags' => [$tag->id]
            ])
        );

        return $discussion;
    }
}