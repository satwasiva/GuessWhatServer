<?php
class Puzzle extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('puzzle_model');
	}

	public function index()
	{
		$data['puzzles'] = $this->puzzle_model->get_puzzles();
		$this->load->view('puzzle', $data);
	}

	public function view($slug)
	{
		$data['puzzles'] = $this->puzzle_model->get_puzzles($slug);
	}
}
