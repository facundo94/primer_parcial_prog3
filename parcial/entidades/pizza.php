<?php

    class Pizza {
        public $tipo;
        public $precio;
        public $stock;
        public $sabor;
        public $foto;
        
        public function __construct($tipo, $precio, $stock, $sabor, $foto){
            $this->tipo = $tipo;
            $this->precio = $precio;
            $this->stock = $stock;
            $this->sabor = $sabor;
            $this->foto = $foto;
        }
    }

    function Show($user){
        return '{"tipo":' . $this->tipo . ', "precio":' . $this->precio . ', "sabor":' . $this->sabor . ', "foto":' . $this->foto . 
            $user == 'encargado' ? ', "tipo":' . $this->tipo : '' . ' }';
    }
?>