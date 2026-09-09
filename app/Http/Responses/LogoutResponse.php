<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Após o logout do painel, leva o usuário de volta para a landing page
 * (a página inicial deslogada) em vez da tela de login padrão do Filament.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->route('landing');
    }
}
