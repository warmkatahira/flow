<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BoardController extends Controller
{
    /**
     * ボード一覧（カンバンは次フェーズで実装）
     */
    public function index(): View
    {
        return view('boards.index');
    }
}
