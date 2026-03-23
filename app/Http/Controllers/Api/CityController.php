<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $query = City::where('country_code', 'RU')
                     ->where('population', '>', 100000);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('alternatenames', 'like', "%{$searchTerm}%");
            });
        }

        $cities = $query->get();

        $cities->transform(function ($city) {
            if (empty($city->alternatenames)) {
                return $city;
            }

            $alternateNames = explode(',', $city->alternatenames);

            // 1. Найти все кириллические варианты
            $cyrillicNames = array_filter($alternateNames, function ($name) {
                return preg_match('/[а-яА-ЯЁё]/u', $name);
            });

            if (empty($cyrillicNames)) {
                return $city;
            }

            // 2. Отфильтровать только те, что содержат исключительно русские буквы
            $russianNames = array_filter($cyrillicNames, function ($name) {
                // Этот regex проверяет, что строка состоит только из русских букв, пробелов и дефисов
                return preg_match('/^[а-яА-ЯёЁ\s\-]+$/u', $name);
            });

            $bestName = null;

            if (!empty($russianNames)) {
                // 3. Если есть чисто русские имена, выбираем лучшее из них
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

            // 4. Если нашли подходящее имя, подставляем его
            if ($bestName) {
                $city->name = $bestName;
            }
            // Если чисто русских имен не нашлось, оставляем оригинальное имя ($city->name),
            // чтобы избежать вывода "Тулă" и подобных.

            return $city;
        });

        return $cities;
    }
}
