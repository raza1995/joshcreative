<?php
namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Storage;
use Google\Auth\OAuth2;
use Google\Auth\Credentials\UserRefreshCredentials;

class GmailAuthService
{
    private $client;
    private $tokenPath;

    public function __construct()
    {
        $this->tokenPath = storage_path('app/token.json');

        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/credentials.json'));
        $this->client->addScope(Gmail::MAIL_GOOGLE_COM);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setRedirectUri('http://localhost'); // Redirect URI

        $this->authenticate();
    }

    private function authenticate()
    {
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);

            // Refresh token if expired
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    $this->client->setAccessToken($newAccessToken);
                    file_put_contents($this->tokenPath, json_encode($newAccessToken));
                } else {
                    throw new \Exception("Google API requires re-authentication.");
                }
            }
        } else {
            $authUrl = $this->client->createAuthUrl();
            echo "Open this URL in your browser and authenticate: \n$authUrl\n";
            echo "Paste the authentication code here: ";
            $authCode = trim(fgets(STDIN));

            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            file_put_contents($this->tokenPath, json_encode($accessToken));
            $this->client->setAccessToken($accessToken);
        }
    }

    public function getClient()
    {
        return $this->client;
    }
}
