<?php
include "vendor/autoload.php";
include_once "./entidades/user.php";
include_once "./entidades/pizza.php";
include_once "./entidades/venta.php";

use \Firebase\JWT\JWT;

define("SECRET_KEY", "pro3-parcial");
define("USERS_FILE", "files/users.xxx");
define("PIZZAS_FILE", "files/pizzas.xxx");
define("SALES_FILE", "files/ventas.xxx");

$path_info = $_SERVER['PATH_INFO'];
$request_method = $_SERVER['REQUEST_METHOD'];

switch ($path_info) {
    case '/usuario':
        if ($request_method == 'POST') {
            addUser();
        }
        break;

    case '/login':
        if ($request_method == 'POST') {
            login();
        }
        break;

    case '/pizzas':
        if ($request_method == 'POST') {
            addPizza();
        } elseif ($request_method == 'GET') {
            getAllPizzas();
        }
        break;

    case '/ventas':
        if ($request_method == 'POST') {
            buyPizza();
        } elseif ($request_method == 'GET') {
            getSalesInfo();
        }
        break;
}

function addUser()
{
    $users = unserializeFromFile(USERS_FILE);

    $res = new stdClass();
    $res->success = false;
    $user = new User($_POST['email'], $_POST['clave'], $_POST['tipo']);

    if (!checkFields(['email', 'clave', 'tipo'])) {
        $res->data = "Faltan datos necesesarios para la creacion de un usuario.";
    } else {
        if (checkIfExists($users, ['email']) == false) {
            array_push($users, $user);

            serializeIntoFile(USERS_FILE, $users);

            $res->success = true;
            $res->data = $users;
        } else {
            $res->data = "El email ya esta registrado.";
        }
    }


    echo json_encode($res);
}

function login()
{
    $users = unserializeFromFile(USERS_FILE);

    $res = new stdClass();

    if (!checkFields(['email', 'clave'])) {
        $res->success = false;
        $res->data = "Faltan datos necesesarios para el login.";
    } else {
        $found = checkIfExists($users, ['email', 'clave']);
        $res->success = $found != false;

        if ($res->success) {
            $jwt = JWT::encode($found, SECRET_KEY);
            $res->data = $jwt;
        } else {
            $res->data = "Error en Email o Clave.";
        }
    }

    echo json_encode($res);
}

function addPizza()
{
    $res = new stdClass();
    $res->success = false;
    $data = validateJWT();

    if ($data != false) {
        if ($data->tipo == "encargado") {
            $products = savePizza();
            $res->success = $products != false;
            if ($res->success) {
                $res->data = $products;
            } else {
                $res->data = "Error al ingresar la pizza.";
            }
        } else {
            $res->data = 'Necesita ser administrador para utilizar este servicio.';
        }
    }

    echo json_encode($res);
}

function savePizza()
{
    $pizzas = unserializeFromFile(PIZZAS_FILE);

    if (!checkFields(['tipo', 'precio', 'stock', 'sabor']) || checkIfExists($pizzas, ['tipo', 'sabor']) != false) {
        return false;
    } else {
        $origen = $_FILES['foto']['tmp_name'];
        $image_name = explode(".", $_FILES['foto']['name']);
        $extension = end($image_name);
        $destino = './images/' . $_POST['tipo'] . '-' . $_POST['sabor'] . '.' . $extension;
        move_uploaded_file($origen, $destino);

        $pizza = new Pizza($_POST['tipo'], $_POST['precio'], $_POST['stock'], $_POST['sabor'], $destino);
        array_push($pizzas, $pizza);

        serializeIntoFile(PIZZAS_FILE, $pizzas);
        return $pizzas;
    }
}

function getAllPizzas()
{
    $data = validateJWT();

    if ($data != false) {
        $pizzas = unserializeFromFile(PIZZAS_FILE);
        $array_to_show = array();

        foreach ($pizzas as $pizza) {
            if ($data->tipo == "cliente") {
                unset($pizza->stock);
            }
            array_push($array_to_show, $pizza);
        }

        echo json_encode($array_to_show);
    }
}

function buyPizza()
{
    $data = validateJWT();
    $res = new stdClass();
    $res->success = false;

    if (!checkFields(['tipo', 'sabor'])) {
        $res->data = "Faltan datos.";
    } elseif ($data != false && $data->tipo == "cliente") {
        $pizzas = unserializeFromFile(PIZZAS_FILE);

        for ($i = 0; $i < sizeof($pizzas); $i++) {
            if ($pizzas[$i]->tipo == $_POST['tipo'] && $pizzas[$i]->sabor == $_POST['sabor'] && $pizzas[$i]->stock >= 0) {
                $pizzas[$i]->stock--;

                $sales = unserializeFromFile(SALES_FILE);
                $sale = new Venta($data->email, $pizzas[$i]->tipo, $pizzas[$i]->sabor, $pizzas[$i]->precio, date("Y/m/d"));
                array_push($sales, $sale);
                serializeIntoFile(SALES_FILE, $sales);
                serializeIntoFile(PIZZAS_FILE, $pizzas);

                $res->success = true;
                $res->data = $sale->monto;
                break;
            }
        }
    } else {
        $res->data = "Usuario no valido.";
    }
    echo json_encode($res);
}

function getSalesInfo()
{
    $data = validateJWT();
    $sales = unserializeFromFile(SALES_FILE);
    $res = new stdClass();
    $res->total = 0;
    $res->salesCount = 0;

    if ($data != false) {
        foreach ($sales as $sale) {
            if ($data->tipo == "encargado" || ($data->tipo == "cliente" && $sale->email == $data->email)) {
                $res->salesCount++;
                $res->total += $sale->monto;
            } 
        }
    }

    echo json_encode($res);
}

function unserializeFromFile($path)
{
    if (filesize($path) > 0) {
        $file = fopen($path, 'r');
        $res = unserialize(fread($file, filesize($path)));
        fclose($file);

        return $res;
    }

    return array();
}

function serializeIntoFile($path, $data)
{
    $file = fopen($path, 'w');
    fwrite($file, serialize($data));
    fclose($file);
}

function checkIfExists($array, $fieldsToCompare)
{
    foreach ($array as $item) {
        $diff = sizeof($fieldsToCompare);
        foreach ($fieldsToCompare as $field) {
            if ($item->{$field} == $_POST[$field]) {
                $diff--;
            }
        }

        if ($diff == 0)
            return $item;
    }

    return false;
}

function checkFields($fields)
{
    foreach ($fields as $field) {
        if (!isset($_POST[$field])) {
            return false;
        }
    }

    return true;
}

function validateJWT()
{
    $jwt = getallheaders()["token"] ?? false;

    if ($jwt != false) {
        try {
            $decoded = JWT::decode($jwt, SECRET_KEY, array("HS256"));

            return $decoded;
        } catch (\Throwable $th) {
            return false;
        }
    }
}