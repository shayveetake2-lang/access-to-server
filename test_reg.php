<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['username' => 'testuser99', 'email' => 'test99@example.com', 'password' => 'password123456'];
require 'api/auth/register.php';
