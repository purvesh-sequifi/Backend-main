<?php

use App\Http\Controllers\API\CalendarEvent\CalendarEventController;

// calendeEventr

Route::post('/add-event', [CalendarEventController::class, 'store']);

Route::post('/event-list', [CalendarEventController::class, 'index']);

Route::put('/edit_event/{id}', [CalendarEventController::class, 'update']);

Route::delete('/delete/{id}', [CalendarEventController::class, 'delete']);

Route::post('/hiring_event_list', [CalendarEventController::class, 'hiringEventList']);
