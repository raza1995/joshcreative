<?php
namespace App\Http\Controllers;

use App\Services\KlaviyoService;
use Illuminate\Http\Request;

class KlaviyoController extends Controller
{
    protected $klaviyoService;

    public function __construct(KlaviyoService $klaviyoService)
    {
        $this->klaviyoService = $klaviyoService;
    }

    public function getProfile(Request $request)
    {
        $email = $request->input('email');
        $profile = $this->klaviyoService->getProfileByEmail($email);

        return response()->json($profile);
    }
}
