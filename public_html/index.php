<?php
require __DIR__ . '/app/nucleo.php';

redirecionar(usuario_atual() ? 'tarefas.php' : 'login.php');
