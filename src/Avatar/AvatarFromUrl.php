<?php

/*
 * Download avatar image from URL and save via Flarum's AvatarUploader.
 */

namespace blt950\OauthGeneric\Avatar;

use Flarum\User\AvatarUploader;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Intervention\Image\ImageManager;

class AvatarFromUrl
{
    /** @var AvatarUploader */
    protected $avatarUploader;
    /** @var Client */
    protected $httpClient;
    /** @var ImageManager */
    protected $imageManager;

    public function __construct(AvatarUploader $avatarUploader, Client $httpClient, ImageManager $imageManager)
    {
        $this->avatarUploader = $avatarUploader;
        $this->httpClient = $httpClient;
        $this->imageManager = $imageManager;
    }

    /**
     * Download image from URL and set as user avatar. No-op if URL is empty or download fails.
     */
    public function syncFromUrl(User $user, ?string $avatarUrl): void
    {
        if ($avatarUrl === null || $avatarUrl === '' || ! $this->isHttpUrl($avatarUrl)) {
            return;
        }

        try {
            $response = $this->httpClient->get($avatarUrl, [
                'timeout' => 10,
                'connect_timeout' => 5,
                'headers' => [
                    'User-Agent' => 'Flarum-OAuth-Generic/1.0',
                ],
            ]);
        } catch (RequestException $e) {
            return;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (! $this->isImageContentType($contentType)) {
            return;
        }

        try {
            $image = $this->imageManager->make($body);
            $this->avatarUploader->upload($user, $image);
            $user->save();
        } catch (\Throwable $e) {
            // Invalid image or upload failed
            return;
        }
    }

    private function isHttpUrl(string $url): bool
    {
        return (substr($url, 0, 7) === 'http://' || substr($url, 0, 8) === 'https://');
    }

    private function isImageContentType(string $contentType): bool
    {
        $main = explode(';', $contentType)[0];
        return in_array(strtolower(trim($main)), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ], true);
    }
}
