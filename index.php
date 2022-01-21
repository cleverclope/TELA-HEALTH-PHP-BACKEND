<?php

    namespace tenna_pharma;

    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Content-type: application/json');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');

    use Exception;
    use Pecee\Http\Middleware\Exceptions\TokenMismatchException;
    use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
    use Pecee\SimpleRouter\SimpleRouter;
    use tenna_health\Readings;
    use tenna_health\Schools;
    use tenna_health\Students;

    spl_autoload_register(function ($className) {
        $classNameParts = explode('\\', $className);
        $className = end($classNameParts);

        $files = ["api/$className.php"];
        foreach ($files as $file) {
            if (file_exists($file)) {
                include $file;
            }
        }
    });

    require_once 'vendor/autoload.php';
    require_once 'utils/utils.php';

    try {
        SimpleRouter::get('/', fn() => 'Hello world');

        SimpleRouter::group(['prefix' => '/schools'], function () {
            SimpleRouter::post('/save', fn() => (new Schools())->save_school());
            SimpleRouter::post('/login', fn() => (new Schools())->login_school());
            SimpleRouter::get('/get', fn() => (new Schools())->get_schools());
        });

        SimpleRouter::group(['prefix' => '/students'], function () {
            SimpleRouter::post('/save', fn() => (new Students())->save_student());
            SimpleRouter::get('/get', fn() => (new Students())->get_students());
        });

        SimpleRouter::group(['prefix' => '/readings'], function () {
            SimpleRouter::group(['prefix' => '/save'], function () {
                SimpleRouter::post('/readings', fn() => (new Readings())->save_readings());
                SimpleRouter::post('/data', fn() => (new Readings())->save_readings_data());
            });

            SimpleRouter::group(['prefix' => '/get'], function () {
                SimpleRouter::post('/readings', fn() => (new Readings())->get_readings());
                SimpleRouter::post('/report', fn() => (new Readings())->get_report());
            });
        });

        SimpleRouter::start();
    } catch (TokenMismatchException | NotFoundHttpException | Exception $e) {
        echo $e->getMessage();
    }

