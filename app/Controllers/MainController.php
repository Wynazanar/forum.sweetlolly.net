<?php
namespace App\Controllers;

use Core\Controller;

class MainController extends Controller
{
	public function index()
	{
		$this->title = 'Главная | SweetLolly Форум';

		return $this->render('main/index');
	}
}