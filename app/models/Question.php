<?php

class Question extends Eloquent
{
    protected $primaryKey = 'question_id';

    protected $table = 'questions';

    public function answers()
    {
        return $this->hasMany('Answer', 'question_id', 'question_id');
    }
}