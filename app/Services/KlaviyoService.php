<?php
namespace App\Services;

use Klaviyo\Klaviyo;
use Klaviyo\Model\ProfileModel;
use KlaviyoAPI\KlaviyoAPI;

class KlaviyoService
{
    protected $klaviyo;

    public function __construct()
    {
        $this->klaviyo = new KlaviyoAPI(env('KLAVIYO_API_KEY'));
    }

    public function getProfileByEmail($email)
    {
        return $this->klaviyo->Profiles->getProfile($email);
    }

    public function createOrUpdateProfile(array $profileData)
    {
        $profile = new ProfileModel($profileData);
        return $this->klaviyo->Profiles->createOrUpdateProfile($profile);
    }
}
