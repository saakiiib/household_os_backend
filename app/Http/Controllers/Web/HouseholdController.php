<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesWebHousehold;
use App\Services\HouseholdProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HouseholdController extends Controller
{
    use ResolvesWebHousehold;

    /**
     * Show the "create your household" step. Users who already have a
     * household go straight to plans.
     */
    public function show(Request $request)
    {
        if ($this->webHousehold($request)) {
            return redirect()->route('billing.plans');
        }

        return view('web.household');
    }

    /**
     * Create the household the user will subscribe for, with the same
     * guards and 30-day trial as the mobile API. One household per user,
     * so this can never create a duplicate or a second trial.
     */
    public function store(Request $request)
    {
        if ($this->webHousehold($request)) {
            return redirect()->route('billing.plans');
        }

        $validated = $request->validate([
            'household_name' => 'required|string|min:2|max:100',
        ]);

        try {
            $household = (new HouseholdProvisioningService())->provision(
                $request->user(),
                $validated['household_name']
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['household_name' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            Log::error('Web household creation failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['household_name' => 'Could not create your household. Please try again.'])->withInput();
        }

        $this->pinWebHousehold($request, $household->id);

        return redirect()->route('billing.plans');
    }
}
