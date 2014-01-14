<?php

class User extends Record
{
	const GUEST = 1;

	public function __construct($id=0)
	{
		parent::__construct('users', $id);
	}

	public function save()
	{
		if ($this->id === GUEST)
			return;

		parent::save();
	}

	public function delete()
	{
		if ($this->id !== GUEST)
			$this->deactivate();
	}
}

