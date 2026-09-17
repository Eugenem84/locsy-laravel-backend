<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CityController extends Controller
{
    /**
     * Русские названия для городов, где автоподбор ошибается.
     *
     * Русское имя выбирается из alternatenames по схожести с asciiname (см. pickRussianName),
     * но GeoNames хранит там и обратные транслитерации латинского имени — «Одинтсово»,
     * «Шчолково», «Москох», «Казан». Они «похожи» на asciiname сильнее правильных
     * написаний, поэтому для таких городов (а также для экзонимов вроде Moscow,
     * у которых asciiname вообще не транслитерация) имя прописываем явно.
     *
     * Правильное решение на будущее — импортировать предпочтительное русское имя
     * из GeoNames alternateNames (isolanguage = 'ru') в отдельную колонку.
     */
    private const CITY_NAME_OVERRIDES = [
        479411 => 'Ухта',                       // Ukhta
        480060 => 'Тверь',                      // Tver
        495344 => 'Щёлково',                    // Shchyolkovo
        498817 => 'Санкт-Петербург',            // Saint Petersburg
        511196 => 'Пермь',                      // Perm
        515012 => 'Орёл',                       // Orel
        516215 => 'Одинцово',                   // Odintsovo
        517836 => 'Новотроицк',                 // Novotroitsk
        519336 => 'Великий Новгород',           // Velikiy Novgorod
        524901 => 'Москва',                     // Moscow
        551487 => 'Казань',                     // Kazan
        554233 => 'Королёв',                    // Korolev
        558418 => 'Грозный',                    // Grozny
        563514 => 'Элиста',                     // Elista
        569223 => 'Череповец',                  // Cherepovets
        580497 => 'Астрахань',                  // Astrakhan
        1488754 => 'Тюмень',                    // Tyumen
        1496990 => 'Новокузнецк',               // Novokuznetsk
        1497337 => 'Норильск',                  // Norilsk
        1503277 => 'Киселёвск',                 // Kiselevsk
        2027456 => 'Артём',                     // Artem
        2122104 => 'Петропавловск-Камчатский',  // Petropavlovsk-Kamchatsky
    ];

    /**
     * Подписи региона, которые не совпадают с данными GeoNames.
     *
     * Москва — город федерального значения: в GeoNames её «регион» — сама Москва,
     * поэтому в скобках получалось «Москва (Москва)». Для списка городов
     * подписываем столицу Московской областью.
     */
    private const CITY_REGION_OVERRIDES = [
        524901 => 'Московская область', // Moscow
    ];

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
                $lowerSearchTerm = mb_strtolower($searchTerm, 'UTF-8');
                $q->whereRaw('LOWER(cities.name) LIKE ?', ["%{$lowerSearchTerm}%"])
                    ->orWhereRaw('LOWER(cities.alternatenames) LIKE ?', ["%{$lowerSearchTerm}%"])
                    ->orWhereRaw('LOWER(admin1_codes.name) LIKE ?', ["%{$lowerSearchTerm}%"])
                    // Добавляем поиск по переведенным названиям регионов
                    ->orWhereRaw('LOWER(admin1_code_translations.name) LIKE ?', ["%{$lowerSearchTerm}%"]);
            });
        }

        // Крупные города — первыми (Москва, Санкт-Петербург и т.д.).
        // Без ORDER BY PostgreSQL отдаёт строки в физическом порядке вставки,
        // поэтому список выглядел произвольным.
        // Вторичная сортировка по name делает порядок детерминированным
        // для городов с одинаковым населением.
        $cities = $query
            ->orderByDesc('cities.population')
            ->orderBy('cities.name')
            ->get();

        $cities->transform(function ($city) {
            $cityName = self::CITY_NAME_OVERRIDES[$city->geonameid] ?? $this->pickRussianName($city);

            // Регион в скобках добавляем, только если он не повторяет название города:
            // «Санкт-Петербург (Санкт-Петербург)» бессмысленно.
            $regionName = self::CITY_REGION_OVERRIDES[$city->geonameid] ?? $city->region_name;

            $city->name = empty($regionName) || $regionName === $cityName
                ? $cityName
                : $cityName.' ('.$regionName.')';

            return $city;
        });

        return $cities;
    }

    /**
     * Русское название города из alternatenames.
     *
     * В поле лежит список вариантов без указания языка, поэтому выбираем самый
     * похожий на asciiname — для большинства городов РФ это верный вариант.
     * Там, где подбор ошибается, название задано в CITY_NAME_OVERRIDES.
     */
    private function pickRussianName(object $city): string
    {
        if (empty($city->alternatenames)) {
            return $city->name; // латинское имя из GeoNames
        }

        $russianNames = array_filter(
            explode(',', $city->alternatenames),
            fn ($name) => preg_match('/^[а-яА-ЯёЁ\s\-]+$/u', $name) === 1
        );

        $bestName = null;
        $bestMatchScore = -1;
        $asciiname = strtolower($city->asciiname ?? '');

        foreach ($russianNames as $name) {
            similar_text($asciiname, strtolower(Str::ascii($name)), $score);
            if ($score > $bestMatchScore) {
                $bestMatchScore = $score;
                $bestName = $name;
            }
        }

        return $bestName ?? $city->name;
    }
}
