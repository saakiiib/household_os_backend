<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('household.{householdId}', function ($user, $householdId) {
    return \App\Models\HouseholdMember::where('household_id', $householdId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});

