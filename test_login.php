<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['username' => 'testuser99', 'password' => 'password123456'];
require 'api/auth/login.php';
