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
    use tenna_health\Attendance;
    use tenna_health\Readings;
    use tenna_health\Schools;
    use tenna_health\Users;

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
            SimpleRouter::post('/classes', fn() => (new Schools())->save_classes());
            SimpleRouter::get('/get', fn() => (new Schools())->get_schools());
            SimpleRouter::post('/login', fn() => (new Schools())->login_school());
            SimpleRouter::get('/init', fn() => (new Schools())->initialise_data());
        });

        SimpleRouter::group(['prefix' => '/users'], function () {
            SimpleRouter::post('/photo', fn() => (new Users())->attach_photo());

            SimpleRouter::group(['prefix' => '/students'], function () {
                SimpleRouter::post('/save', fn() => (new Users())->save_student());
                SimpleRouter::get('/get', fn() => (new Users())->get_students());
            });
            SimpleRouter::group(['prefix' => '/staff'], function () {
                SimpleRouter::post('/save', fn() => (new Users())->save_staff());
                SimpleRouter::get('/get', fn() => (new Users())->get_staffs());
            });
            SimpleRouter::group(['prefix' => '/attendance'], function () {
                SimpleRouter::post('/check_in', fn() => (new Attendance())->save_check_in());
                SimpleRouter::post('/check_out', fn() => (new Attendance())->save_check_out());
                SimpleRouter::get('/lists', fn() => (new Attendance())->get_attendances());
            });
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

