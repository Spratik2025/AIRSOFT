
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuizController;

Route::get('/', function () {
    return view('quiz');
});

Route::post('/generate-quiz', [QuizController::class, 'generate'])
    ->name('quiz.generate');
