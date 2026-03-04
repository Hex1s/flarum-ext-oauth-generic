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

class CreateVotingController extends AbstractCreateController
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

        // Проверяем права доступа
        if (!$actor->hasPermission('discussion.startWithoutApproval')) {
            throw new \Flarum\User\Exception\PermissionDeniedException();
        }

        // Валидация данных
        $requiredFields = ['voting_title', 'user_email', 'project_tag_slug', 'voting_tag_slug'];
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

        // Находим теги
        $projectTag = Tag::where('slug', $data['project_tag_slug'])->first();
        $votingTag = Tag::where('slug', $data['voting_tag_slug'])->first();
        
        if (!$projectTag || !$votingTag) {
            throw new \Flarum\Foundation\ValidationException([
                'tags' => 'Required tags not found'
            ]);
        }

        // Формируем контент из опций голосования
        $content = $data['voting_description'] ?? '';
        if (!empty($data['voting_options']) && is_array($data['voting_options'])) {
            $content .= "\n\nOptions:\n";
            foreach ($data['voting_options'] as $index => $option) {
                $content .= ($index + 1) . ". " . $option['text'] . "\n";
            }
        }

        // Создаем дискуссию от лица пользователя
        $discussion = $this->bus->dispatch(
            new StartDiscussion($flarumUser, [
                'title' => $data['voting_title'],
                'content' => $content,
                'tags' => [$projectTag->id, $votingTag->id]
            ])
        );

        return $discussion;
    }
}