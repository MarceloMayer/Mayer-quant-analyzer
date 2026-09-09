<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Deslogado: mostra a landing page. Logado: vai direto para o painel.
    if (Auth::check()) {
        return redirect('/admin');
    }

    return response()->view('landing');
})->name('landing');

// Alias para a rota "login" esperada pelos middlewares de sessão do Laravel
// (Authenticate / AuthenticateSession). Sem ela, um redirecionamento de
// convidado gera "Route [login] not defined". Aponta para o login do painel.
Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');
