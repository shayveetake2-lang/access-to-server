<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['username' => 'autoregister3', 'password' => 'password123456'];
require 'api/auth/login.php';
