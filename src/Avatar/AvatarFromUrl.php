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
use Psr\Log\LoggerInterface;

class AvatarFromUrl
{
    /** @var AvatarUploader */
    protected $avatarUploader;
    /** @var Client */
    protected $httpClient;
    /** @var ImageManager */
    protected $imageManager;
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        AvatarUploader $avatarUploader,
        Client $httpClient,
        ImageManager $imageManager,
        LoggerInterface $logger
    ) {
        $this->avatarUploader = $avatarUploader;
        $this->httpClient = $httpClient;
        $this->imageManager = $imageManager;
        $this->logger = $logger;
    }

    /**
     * Download image from URL and set as user avatar. No-op if URL is empty or download fails.
     */
    public function syncFromUrl(User $user, ?string $avatarUrl): void
    {
        if ($avatarUrl === null || $avatarUrl === '') {
            $this->logger->debug('[OAuth Generic] Avatar sync skipped: no URL for user ' . $user->id);
            return;
        }
        if (! $this->isHttpUrl($avatarUrl)) {
            $this->logger->debug('[OAuth Generic] Avatar sync skipped: not HTTP(S) URL for user ' . $user->id);
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
            $this->logger->warning('[OAuth Generic] Avatar download failed for user ' . $user->id . ': ' . $e->getMessage());
            return;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            $this->logger->debug('[OAuth Generic] Avatar sync skipped: empty response for user ' . $user->id);
            return;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (! $this->isImageContentType($contentType)) {
            $this->logger->debug('[OAuth Generic] Avatar sync skipped: unsupported Content-Type ' . $contentType . ' for user ' . $user->id);
            return;
        }

        try {
            $image = $this->imageManager->make($body);
            $this->avatarUploader->upload($user, $image);
            $user->save();
            $this->logger->info('[OAuth Generic] Avatar synced from URL for user ' . $user->id);
        } catch (\Throwable $e) {
            $this->logger->warning('[OAuth Generic] Avatar upload failed for user ' . $user->id . ': ' . $e->getMessage());
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
