<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

#[\AllowDynamicProperties]
class LoginController {
    public static function login(Router $router){
        $alertas = [];
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $usuario = new Usuario($_POST);
            $alertas = $usuario->validarLogin();

            if(empty($alertas)) {
                //Verificar que el usario exista
                $usuario = Usuario::where('email', $usuario->email);

                if(!$usuario || !$usuario->confirmado){
                    Usuario::setAlerta('error', 'El usuario No existe o no esta confirmado');
                } else {
                    //El Usuario existe
                    if(password_verify($_POST['password'], $usuario->password)) {
                        //Iniciar la sesion
                        session_start();
                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        //Redirecciionar
                        header('Location: /dashboard');
                    } else {
                        Usuario::setAlerta('error', 'Password incorrecto');
                    }

                }

            }
            

        }
        $alertas = Usuario::getAlertas();
        //Render a la vista
        $router->render('auth/login', [
            'titulo' => 'Iniciar Sesión',
            'alertas' => $alertas
        ]);
    }

    public static function logout(){
        session_start();
        $_SESSION = [];
        header('Location: /');
    }

    public static function crear(Router $router){
        //Instanciar usuario
        $alertas = [];
        $usuario = new Usuario;

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();

            if(empty($alertas)){
                $existeusuario = Usuario::where('email', $usuario->email);

                if($existeusuario){
                    Usuario::setAlerta('error', 'El usuario ya esta registrado');
                    $alertas = Usuario::getAlertas();    
                } else {
                    //Hashear el password
                    $usuario->hashPassword();

                    //Eliminar password2 porque no se requiere en DB = Base de datos
                    unset($usuario->password2);

                    //Generar token
                    $usuario->crearToken();
                   
                    //Crear nuevo usuario
                    $resultado = $usuario->guardar();

                    //Enviar email
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarConfirmacion();

                    if($resultado) {
                        header('Location: /mensaje');
                    }
                }
            }

        }

        //Render a la vista
        $router->render('auth/crear', [
            'titulo' => 'Crear tu Cuenta en UpTask',
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);

    }

    public static function olvide(Router $router){
        $alertas = [];
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $usuario = new Usuario($_POST);
            $alertas = $usuario->validarEmail();

            if(empty($alertas)){
                //Buscar el usuario
                $usuario = Usuario::where('email', $usuario->email);

                if($usuario && $usuario->confirmado){
                    //Encontro el usuario
                    //1º Generar un nuevo token
                    $usuario->crearToken();
                    unset($usuario->password2);

                    //2º Actualizar el usuario
                    $usuario->guardar();

                    //3º Envial el email
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarInstrucciones();


                    //4ª Imprimir la alerta
                    Usuario::setAlerta('exito', 'Hemos enviado las instrucciones a tu Email');

                } else {
                    Usuario::setAlerta('error', 'El usuario no existe o no esta confirmado');
                    
                }
            }

        }
        
        $alertas = Usuario::getAlertas();

        //Muestra la vista
        $router->render('auth/olvide', [
            'titulo' => 'Olvide mi Password',
            'alertas' => $alertas
        ]);    

    }

    public static function reestablecer(Router $router) {

        $token = s($_GET['token']);
        $mostrar = true;

        if(!$token) header('Location: /');

        // Identificar el usuario con este token
        $usuario = Usuario::where('token', $token);

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token No Válido');
            $mostrar = false;
        }


        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Añadir el nuevo password
            $usuario->sincronizar($_POST);

            // Validar el password
            $alertas = $usuario->validarPassword();

            if(empty($alertas)) {
                // Hashear el nuevo password                
                $usuario->hashPassword();
                //Eliminar password2 porque no se requiere en DB osea Base de datos
                unset($usuario->password2);                
                // Eliminar el Token
                $usuario->token = '';
                // Guardar el usuario en la BD
                $resultado = $usuario->guardar();
                // Redireccionar
                if($resultado) {
                    header('Location: /');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        //Muestra la vista
        $router->render('auth/reestablecer', [
            'titulo' => 'Restablecer Password',
            'alertas' => $alertas,
            'mostrar' => $mostrar
        ]);


    }
    public static function mensaje(Router $router){
                //Muestra la vista
        $router->render('auth/mensaje', [
            'titulo' => 'Cuenta Creada Exitosamente'
        ]);
    }

    public static function confirmar(Router $router) {
        
        $token = s($_GET['token']);

        if(!$token) header('Location: /');

        // Encontrar al usuario con este token
        $usuario = Usuario::where('token', $token);

        if(empty($usuario)) {
            // No se encontró un usuario con ese token
            Usuario::setAlerta('error', 'Token No Válido');
        } else {
            // Confirmar la cuenta
            $usuario->confirmado = 1;
            $usuario->token = '';
            unset($usuario->password2);
            
            // Guardar en la BD
            $usuario->guardar();

            Usuario::setAlerta('exito', 'Cuenta Creada Correctamente');
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/confirmar', [
            'titulo' => 'Confirma tu cuenta UpTask',
            'alertas' => $alertas
        ]);
    }

}