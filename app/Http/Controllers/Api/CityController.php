<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $locale = 'ru'; // Устанавливаем локаль для переводов

        $query = DB::table('cities')
            ->leftJoin('admin1_codes', function ($join) {
                $join->on('cities.admin1_code', '=', 'admin1_codes.admin1_code')
                    ->on('cities.country_code', '=', 'admin1_codes.country_code');
            })
            ->leftJoin('admin1_code_translations', function ($join) use ($locale) {
                $join->on('admin1_codes.id', '=', 'admin1_code_translations.admin1_code_id')
                    ->where('admin1_code_translations.locale', '=', $locale);
            })
            ->where('cities.country_code', 'RU')
            ->where('cities.population', '>', 100000)
            ->select(
                'cities.*',
                // Используем COALESCE, чтобы выбрать русский перевод, если он есть, иначе - английское название
                DB::raw('COALESCE(admin1_code_translations.name, admin1_codes.name) as region_name')
            );


        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('cities.name', 'like', "%{$searchTerm}%")
                    ->orWhere('cities.alternatenames', 'like', "%{$searchTerm}%")
                    ->orWhere('admin1_codes.name', 'like', "%{$searchTerm}%")
                    // Добавляем поиск по переведенным названиям регионов
                    ->orWhere('admin1_code_translations.name', 'like', "%{$searchTerm}%");
            });
        }

        $cities = $query->get();

        $cities->transform(function ($city) {
            $cityName = $city->name; // Изначально - латинское имя

            if (!empty($city->alternatenames)) {
                $alternateNames = explode(',', $city->alternatenames);
                $russianNames = array_filter($alternateNames, function ($name) {
                    return preg_match('/^[а-яА-ЯёЁ\s\-]+$/u', $name);
                });

                $bestName = null;
                if (!empty($russianNames)) {
                    $bestMatchScore = -1;
                    foreach ($russianNames as $name) {
                        $transliteratedName = Str::ascii($name);
                        similar_text(strtolower($city->asciiname), strtolower($transliteratedName), $score);
                        if ($score > $bestMatchScore) {
                            $bestMatchScore = $score;
                            $bestName = $name;
                        }
                    }
                }
                if ($bestName) {
                    $cityName = $bestName;
                }
            }

            // Формируем новое имя, только если регион найден
            // Теперь region_name будет содержать русский перевод
            if (!empty($city->region_name)) {
                $city->name = $cityName . ' (' . $city->region_name . ')';
            } else {
                $city->name = $cityName;
            }

            return $city;
        });

        return $cities;
    }
}
