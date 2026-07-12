<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public: data for the landing page (no auth) — real DB data instead of static content
Route::get('/public/doctors', [PublicController::class, 'doctors']);
Route::get('/public/specialities', [PublicController::class, 'specialities']);
Route::get('/public/stats', [PublicController::class, 'stats']);

// Public: serve files from the "public" storage disk (avatars, etc.)
// Works even if `php artisan storage:link` was never run or symlinks aren't
// supported by the hosting environment.
Route::get('/storage/{path}', function ($path) {
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Current user's own profile (view / edit info + photo)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{doctor}', [DoctorController::class, 'show']);
    Route::get('/doctors/{doctor}/availability', [DoctorController::class, 'availability']);
    Route::get('/doctors/{doctor}/stats', [DoctorController::class, 'stats']);
    Route::put('/doctors/{doctor}', [DoctorController::class, 'update']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);

    // Chat between patient and doctor, scoped to a specific appointment
    Route::get('/messages/conversations', [\App\Http\Controllers\Api\MessageController::class, 'conversations']);
    Route::get('/appointments/{appointment}/messages', [\App\Http\Controllers\Api\MessageController::class, 'index']);
    Route::post('/appointments/{appointment}/messages', [\App\Http\Controllers\Api\MessageController::class, 'store']);

    Route::get('/patients/{patient}', [PatientController::class, 'show']);
    Route::put('/patients/{patient}', [PatientController::class, 'update']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::put('/invoices/{id}/pay', [\App\Http\Controllers\Api\InvoiceController::class, 'pay']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

    Route::get('/consultations', [ConsultationController::class, 'index']);
    Route::post('/consultations', [ConsultationController::class, 'store']);
    Route::get('/consultations/{id}', [ConsultationController::class, 'show']);
    Route::put('/consultations/{id}', [ConsultationController::class, 'update']);
    Route::delete('/consultations/{id}', [ConsultationController::class, 'destroy']);

    Route::middleware('role:admin,secretary,doctor')->group(function () {
        Route::get('/patients', [PatientController::class, 'index']);
        Route::put('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    });

    Route::middleware('role:admin,secretary')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/staff', [AuthController::class, 'createStaff']);
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy']);

        // Admin: manage all users (list, edit, delete)
        Route::get('/admin/users', [ProfileController::class, 'allUsers']);
        Route::put('/admin/users/{id}', [ProfileController::class, 'adminUpdate']);
        Route::delete('/admin/users/{id}', [ProfileController::class, 'adminDelete']);
    });
});