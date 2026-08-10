<?php

namespace App\Services;

use App\Models\House;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WeatherService
{
    public function currentFor(House $house): ?array
    {
        if ($house->weather_latitude === null || $house->weather_longitude === null) {
            return null;
        }

        try {
            $data = Cache::flexible("weather.house.{$house->id}.v2", [900, 3600], fn () => Http::connectTimeout(2)->timeout(4)->retry(1, 150)
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $house->weather_latitude,
                    'longitude' => $house->weather_longitude,
                    'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                    'forecast_days' => 5,
                    'timezone' => 'auto',
                ])->throw()->json());

            $current = $data['current'];
            [$icon, $description] = $this->describe((int) $current['weather_code'], (bool) $current['is_day']);
            $forecast = collect($data['daily']['time'])->map(function ($date, $index) use ($data) {
                [$icon, $description] = $this->describe((int) $data['daily']['weather_code'][$index], true);

                return [
                    'day' => $index === 0 ? 'Hoy' : str(CarbonImmutable::parse($date)->locale('es')->isoFormat('ddd'))->ucfirst(),
                    'date' => CarbonImmutable::parse($date)->format('d/m'),
                    'icon' => $icon,
                    'description' => $description,
                    'max' => round($data['daily']['temperature_2m_max'][$index]),
                    'min' => round($data['daily']['temperature_2m_min'][$index]),
                    'rain' => round($data['daily']['precipitation_probability_max'][$index] ?? 0),
                ];
            })->all();

            return [
                'location' => $house->weather_location ?: 'En casa',
                'temperature' => round($current['temperature_2m']),
                'apparent' => round($current['apparent_temperature']),
                'humidity' => round($current['relative_humidity_2m']),
                'wind' => round($current['wind_speed_10m']),
                'max' => round($data['daily']['temperature_2m_max'][0]),
                'min' => round($data['daily']['temperature_2m_min'][0]),
                'rain' => round($data['daily']['precipitation_probability_max'][0] ?? 0),
                'icon' => $icon,
                'description' => $description,
                'forecast' => $forecast,
            ];
        } catch (Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function describe(int $code, bool $isDay): array
    {
        return match (true) {
            $code === 0 => [$isDay ? '☀️' : '🌙', 'Despejado'],
            in_array($code, [1, 2], true) => [$isDay ? '🌤️' : '☁️', 'Poco nuboso'],
            $code === 3 => ['☁️', 'Cubierto'],
            in_array($code, [45, 48], true) => ['🌫️', 'Niebla'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['🌦️', 'Llovizna'],
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => ['🌧️', 'Lluvia'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['🌨️', 'Nieve'],
            in_array($code, [95, 96, 99], true) => ['⛈️', 'Tormenta'],
            default => ['🌡️', 'Tiempo variable'],
        };
    }
}
