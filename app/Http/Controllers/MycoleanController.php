<?php

namespace App\Http\Controllers;

use App\Models\SoberDay;
use App\Models\MycoleanUser;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MycoleanController extends Controller
{
    private function resolveUser(string $email): MycoleanUser
    {
        // Create or find Mycolean user by email (separate table from app users)
        $norm = mb_strtolower(trim($email));
        $displayName = explode('@', $norm)[0] ?: 'Mycolean User';
        $user = MycoleanUser::firstOrCreate(
            ['email' => $norm],
            ['name' => $displayName]
        );

        return $user;
    }

    private function nowForTz(?string $tz): CarbonImmutable
    {
        try {
            return CarbonImmutable::now($tz ?: 'UTC');
        } catch (\Throwable $e) {
            return CarbonImmutable::now('UTC');
        }
    }

    private function monthKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    private function daysInMonth(int $year, int $month): int
    {
        return Carbon::create($year, $month, 1)->daysInMonth;
    }

    private function rangeForMonth(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();
        return [$start, $end];
    }

    public function getMonth(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'email' => ['required','email:rfc'],
            'year' => ['nullable','integer','min:2000','max:2100'],
            'month' => ['nullable','integer','min:1','max:12'],
            'tz' => ['nullable','string'],
        ]);

        $now = $this->nowForTz($data['tz'] ?? null);
        $year = (int)($data['year'] ?? $now->year);
        $month = (int)($data['month'] ?? $now->month);
        $dim = $this->daysInMonth($year, $month);

        $user = $this->resolveUser($data['email']);
        [$start, $end] = $this->rangeForMonth($year, $month);

        $days = array_fill(0, $dim, false);
        SoberDay::query()
            ->where('mycolean_user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->each(function (SoberDay $sd) use (&$days) {
                $dayIdx = (int)Carbon::parse($sd->date)->day - 1;
                if ($dayIdx >= 0 && $dayIdx < count($days)) {
                    $days[$dayIdx] = true;
                }
            });

        $todayMarked = false;
        if ($now->year === $year && $now->month === $month) {
            $todayMarked = SoberDay::query()
                ->where('mycolean_user_id', $user->id)
                ->whereDate('date', $now->toDateString())
                ->exists();
        }

        $nextEligibleAt = $now->copy()->addDay()->startOfDay();

        return response()->json([
            'monthKey' => $this->monthKey($year, $month),
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'todayMarked' => $todayMarked,
            'serverNow' => $now->toIso8601String(),
            'nextEligibleAt' => $nextEligibleAt->toIso8601String(),
        ]);
    }

    public function markToday(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'email' => ['required','email:rfc'],
            'tz' => ['nullable','string'],
        ]);

        $now = $this->nowForTz($data['tz'] ?? null);
        $user = $this->resolveUser($data['email']);
        $date = $now->toDateString();

        $exists = SoberDay::query()
            ->where('mycolean_user_id', $user->id)
            ->whereDate('date', $date)
            ->exists();

        if (!$exists) {
            SoberDay::create([
                'mycolean_user_id' => $user->id,
                'date' => $date,
            ]);
        }

        $nextEligibleAt = $now->copy()->addDay()->startOfDay();
        [$year, $month] = [$now->year, $now->month];
        $dim = $this->daysInMonth($year, $month);

        $days = array_fill(0, $dim, false);
        [$start, $end] = $this->rangeForMonth($year, $month);
        SoberDay::query()
            ->where('mycolean_user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (SoberDay $sd) use (&$days) {
                $days[(int)Carbon::parse($sd->date)->day - 1] = true;
            });

        return response()->json([
            'status' => $exists ? 'already_marked' : 'marked',
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'todayMarked' => true,
            'serverNow' => $now->toIso8601String(),
            'nextEligibleAt' => $nextEligibleAt->toIso8601String(),
        ]);
    }

    public function toggleDay(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'email' => ['required','email:rfc'],
            'year' => ['required','integer','min:2000','max:2100'],
            'month' => ['required','integer','min:1','max:12'],
            'day' => ['required','integer','min:1','max:31'],
            'value' => ['required','boolean'],
            'tz' => ['nullable','string'],
        ]);

        $user = $this->resolveUser($data['email']);
        $now = $this->nowForTz($data['tz'] ?? null);
        $dim = $this->daysInMonth((int)$data['year'], (int)$data['month']);
        if ((int)$data['day'] < 1 || (int)$data['day'] > $dim) {
            return response()->json(['error' => 'Invalid day for month'], 422);
        }
        $target = CarbonImmutable::create($data['year'], $data['month'], $data['day'])->startOfDay();

        // Disallow future days
        if ($target->greaterThan($now->startOfDay())) {
            return response()->json(['error' => 'Cannot toggle future days'], 422);
        }

        $isToday = $target->isSameDay($now);
        // Enforce: Today can only be marked once and cannot be undone the same day
        if ($isToday) {
            if (!$data['value']) {
                return response()->json(['error' => 'Cannot undo today. Wait until tomorrow to adjust.'], 422);
            }
            // attempt to mark; if exists, treat as already marked
            SoberDay::firstOrCreate([
                'mycolean_user_id' => $user->id,
                'date' => $target->toDateString(),
            ]);
        } else {
            // Past day: allow toggle on/off
            if ($data['value']) {
                SoberDay::firstOrCreate([
                    'mycolean_user_id' => $user->id,
                    'date' => $target->toDateString(),
                ]);
            } else {
                SoberDay::where('mycolean_user_id', $user->id)
                    ->whereDate('date', $target->toDateString())
                    ->delete();
            }
        }

        // Respond with month state
        [$start, $end] = $this->rangeForMonth((int)$data['year'], (int)$data['month']);
        $dim = $this->daysInMonth((int)$data['year'], (int)$data['month']);
        $days = array_fill(0, $dim, false);
        SoberDay::where('mycolean_user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (SoberDay $sd) use (&$days) {
                $days[(int)Carbon::parse($sd->date)->day - 1] = true;
            });

        $todayMarked = SoberDay::where('mycolean_user_id', $user->id)->whereDate('date', $now->toDateString())->exists();
        $nextEligibleAt = $now->copy()->addDay()->startOfDay();

        return response()->json([
            'year' => (int)$data['year'],
            'month' => (int)$data['month'],
            'days' => $days,
            'todayMarked' => $todayMarked,
            'serverNow' => $now->toIso8601String(),
            'nextEligibleAt' => $nextEligibleAt->toIso8601String(),
        ]);
    }

    public function syncMonth(Request $request)
    {
        $data = Validator::validate($request->all(), [
            'email' => ['required','email:rfc'],
            'year' => ['required','integer','min:2000','max:2100'],
            'month' => ['required','integer','min:1','max:12'],
            'days' => ['required','array'],
            'days.*' => ['boolean'],
            'tz' => ['nullable','string'],
        ]);

        $now = $this->nowForTz($data['tz'] ?? null);
        $user = $this->resolveUser($data['email']);
        $year = (int)$data['year'];
        $month = (int)$data['month'];
        $dim = $this->daysInMonth($year, $month);
        $days = array_values(array_slice($data['days'], 0, $dim));
        $days = array_pad($days, $dim, false);

        [$start, $end] = $this->rangeForMonth($year, $month);

        // Build allowed set: up to today (inclusive) if current month, else entire month
        $maxDay = $dim;
        if ($now->year === $year && $now->month === $month) {
            $maxDay = min($dim, $now->day);
        }

        // Existing entries for month
        $existing = SoberDay::where('mycolean_user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(function (SoberDay $sd) {
                return (int)Carbon::parse($sd->date)->day;
            });

        // Apply desired state for allowed days
        for ($i = 1; $i <= $maxDay; $i++) {
            $want = (bool)$days[$i - 1];
            $has = $existing->has($i);
            $dateStr = CarbonImmutable::create($year, $month, $i)->toDateString();
            if ($want && !$has) {
                SoberDay::create(['mycolean_user_id' => $user->id, 'date' => $dateStr]);
            } elseif (!$want && $has) {
                SoberDay::where('mycolean_user_id', $user->id)->whereDate('date', $dateStr)->delete();
            }
        }

        // Return month state
        $out = array_fill(0, $dim, false);
        SoberDay::where('mycolean_user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (SoberDay $sd) use (&$out) {
                $out[(int)Carbon::parse($sd->date)->day - 1] = true;
            });

        $todayMarked = SoberDay::where('mycolean_user_id', $user->id)->whereDate('date', $now->toDateString())->exists();
        $nextEligibleAt = $now->copy()->addDay()->startOfDay();

        return response()->json([
            'year' => $year,
            'month' => $month,
            'days' => $out,
            'todayMarked' => $todayMarked,
            'serverNow' => $now->toIso8601String(),
            'nextEligibleAt' => $nextEligibleAt->toIso8601String(),
        ]);
    }
}
