<?php
namespace App\Controllers;

use Core\Controller;
use App\Models\Post;

class ThreadController extends Controller
{
    public function show($params)
    {
        $postModel = new Post();

        $slug = trim(urldecode($params['thread'] ?? ''));

        if ($slug === '') {
            return $this->error(404);
        }

        $thread = $postModel->getThreadBySlug($slug);

        if (!$thread) {
            return $this->error(404);
        }

        $posts = $postModel->findByThread($slug);
        $tree  = $postModel->buildTree($posts);   // ← строим дерево

        $this->title = $thread['title'] . ' | SweetLolly Форум';

        return $this->render('post/index', [
            'thread' => $thread,
            'posts'  => $tree,                    // ← передаём дерево
        ]);
    }
}