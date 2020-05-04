<?php

    class Venta {
        public $email;
        public $tipo;
        public $sabor;
        public $monto;
        public $fecha;
        
        public function __construct($email, $tipo, $sabor, $monto, $fecha){
            $this->email = $email;
            $this->tipo = $tipo;
            $this->sabor = $sabor;
            $this->monto = $monto;
            $this->fecha = $fecha;
        }
    }
?>