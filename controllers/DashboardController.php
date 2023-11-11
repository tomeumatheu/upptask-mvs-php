<?php

namespace Controllers;

use MVC\Router;
use Model\Usuario;
use Model\Proyecto;


#[\AllowDynamicProperties]
class DashboardController {
    public static function index(Router $router) {

        session_start();
        isAuth();

        $id = $_SESSION['id'];
        $proyectos = Proyecto::belongsTo('propietarioId', $id);
        

        $router->render('dashboard/index', [
            'titulo' => 'Proyectos',
            'proyectos' => $proyectos
        ]);
    }

    public static function crear_proyecto(Router $router) {
        session_start();
        isAuth();
        $alertas = [];

        if($_SERVER['REQUEST_METHOD']  === 'POST'){
            $proyecto = new Proyecto($_POST);
            
            //Validacion
            $alertas = $proyecto->validarProyecto();

            if(empty($alertas)) {
                //Guardar el proyecto
                $hash = md5(uniqid());
                $proyecto->url = $hash;

                //Almacenar el creador del proyecto
                $proyecto->propietarioId = $_SESSION['id'];
                //Guardar el proyecto
                $proyecto->guardar();
                //Redireccionar
                header('Location: /proyecto?id=' . $proyecto->url);

            }
        }

        $router->render('dashboard/crear-proyecto', [
            'alertas' => $alertas,
            'titulo' => 'Crear Proyecto'
            
        ]);
    }

    public static function proyecto(Router $router){
        session_start();
        isAuth();

        $token = $_GET['id'];
        
        if(!$token) header(('Location: /dashboard'));       
        //Revisar que la persona que visita el proyecto es quien lo creo
        $proyecto = Proyecto::where('url', $token);
        if($proyecto->propietarioId !== $_SESSION['id']){
            header(('Location: /dashboard'));
        }


        $router->render('dashboard/proyecto', [
            'titulo' => $proyecto->proyecto
        ]);
    }

    public static function perfil(Router $router) {
        session_start();
        isAuth();
        $alertas = [];

        $usuario = Usuario::find($_SESSION['id']);
   

        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            $usuario->sincronizar($_POST);
            $alertas = $usuario->validar_perfil();

            if (empty($alertas)) {

                $existeUsuario = Usuario::where('email', $usuario->email);
                if($existeUsuario && $existeUsuario->id !== $usuario->id) {
                    //Mensaje Error
                    Usuario::setAlerta('error', 'Email no valido, ya existe, eliga otro');
                    $alertas = $usuario->getAlertas();
                } else {
                    //Guardar Registro
                    $usuario->guardar();
                    Usuario::setAlerta('exito', 'Guardado Correctamente');
                    $alertas = $usuario->getAlertas();
                    //asignar el nombre  nuevo en la barra
                    $_SESSION['nombre'] = $usuario->nombre;
                }

            }

        };

        $router->render('dashboard/perfil', [
            'titulo' => 'Perfil',
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }

    public static function cambiar_password(Router $router) {
        session_start();
        isAuth();

        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = Usuario::find($_SESSION['id']);

            //Sincronizar con los datos del usuario
            $usuario->sincronizar($_POST);

            $alertas = $usuario->nuevo_password();
            
            if(empty($alertas)) {
                $resultado = $usuario->comprobar_password();
                
                if($resultado) {
                    $usuario->password = $usuario->password_nuevo;

                    //Eliminar proiedades no necesarias
                    unset($usuario->password_actual);
                    unset($usuario->password_nuevo);

                    //Hashear el nuevo password
                    $usuario->hashPassword();
                    //Actualizar
                    $resultado = $usuario->guardar();

                    if($resultado) {
                        Usuario::setAlerta('exito', 'Password Guardado Correctamente');
                        $alertas = $usuario->getAlertas();
                    }


                }else {
                    Usuario::setAlerta('error', 'Password Incorrecto');
                    $alertas = $usuario->getAlertas();
                }
            }
        }

        $router->render('dashboard/cambiar-password', [
            'titulo' => 'Cambiar Password',
            'alertas' => $alertas
        ]);
    }
}
