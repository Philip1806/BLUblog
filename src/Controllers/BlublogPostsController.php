<?php

namespace   Blublog\Blublog\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Blublog\Blublog\Models\Post;
use Blublog\Blublog\Models\Tag;
use Blublog\Blublog\Models\Category;
use Blublog\Blublog\Models\File;
use Blublog\Blublog\Models\Log;
use Blublog\Blublog\Models\Rate;
use Blublog\Blublog\Models\Comment;
use Blublog\Blublog\Models\BlublogUser;
use Carbon\Carbon;
use Session;
use Auth;
use Blublog\Blublog\Exceptions\BlublogNoFileDriver;

class BlublogPostsController extends Controller
{
    /**
     * Display a listing of the posts.
     *
     * @return Blublog\Blublog\Models\Post
     */
    public function index()
    {
        $posts = Post::where([
            ['status', '=', 'publish'],
        ])->latest()->paginate(15);
        if (isset($_GET['views'])) {
            $posts = Post::by_views();
        }
        if (isset($_GET['author'])) {
            $posts = Post::by_author();
        }
        $private_posts = Post::where([
            ['status', '=', 'private'],
            ['user_id', '=', Auth::user()->id],
        ])->latest()->paginate(14);
        $draft_posts = Post::where([
            ['status', '=', 'draft'],
        ])->latest()->paginate(14);

        return view("blublog::panel.posts.index")->with('draft_posts', $draft_posts)->with('posts', $posts)->with('private_posts', $private_posts);
    }

    /**
     * Show the form for creating a new post.
     *
     */
    public function create()
    {
        if (!extension_loaded('gd')) {
            Session::flash('error', __('blublog.gd_not_installed'));
            return redirect()->back();
        }
        File::check_driver();
        BlublogUser::check_access('create', Post::class);
        $tags = Tag::latest()->get();
        $categories = Category::latest()->get();
        $date =  Carbon::now()->format('d/m/Y');
        $post_id = Post::next_post_id();
        return view("blublog::panel.posts.create")->with('post_id', $post_id)->with('tags', $tags)->with('date', $date)->with('categories', $categories);
    }


    /**
     * Display the specified post.
     *
     * @param  int  $id
     * @return Blublog\Blublog\Models\Post
     */
    public function show($id)
    {
        try {
            $post = Post::getpost($id);
            BlublogUser::check_access('view', $post);
            $post->rating_votes = Post::rating_votes($post);
            $images = File::where([
                ['descr', '=', $post->id],
                ['is_in_post', '=', true],
            ])->latest()->paginate(14);
            foreach ($images as $file) {
                $file->url = Storage::disk(config('blublog.files_disk', 'blublog'))->url($file->filename);
            }
        } catch (\InvalidArgumentException $exception) {
            throw new BlublogNoFileDriver();
        }
        return view('blublog::panel.posts.show')->with('images', $images)->with('post', $post);
    }
    public function edit($id)
    {
        try {
            $post = Post::getpost($id);
            BlublogUser::check_access('update', $post);
            $date =  Carbon::now()->format('d/m/Y');
            $tags = Tag::all();
            $tags2 = array();
            foreach ($tags as $tag) {
                $tags2[$tag->id] = $tag->title;
            }
            $post->img_url = Storage::disk(config('blublog.files_disk', 'blublog'))->url('posts/' . $post->img);
            $categories = Category::all();
            $categories2 = array();
            foreach ($categories as $category) {
                $categories2[$category->id] = $category->title;
            }
        } catch (\InvalidArgumentException $exception) {
            throw new BlublogNoFileDriver();
        }

        return view("blublog::panel.posts.ed")->with('post_id', $id)->with('tags', $tags2)->with('date', $date)->with('post', $post)->with('categories', $categories2);
    }
    public function store(Request $request)
    {
        BlublogUser::check_access('create', Post::class);
        $rules = [
            'title' => 'required|max:250',
            'categories' => 'required',
            'file' => 'image',
            'content' => 'required',
        ];
        $this->validate($request, $rules);
        $post = Post::create_new($request);
        $post->tags()->sync($request->tags, false);
        $post->categories()->sync($request->categories, false);
        if ($post->status == "publish") {
            Cache::flush();
        }
        Session::flash('success', __('blublog.contentcreate'));
        return redirect()->route('blublog.posts.show', $post->id);
    }
    public function update(Request $request, $id)
    {
        $rules = [
            'title' => 'required|max:250',
            'descr' => 'max:200',
            'categories' => 'required',
            'content' => 'required',
            'slug' => 'max:200',
        ];
        $this->validate($request, $rules);
        $post = Post::edit_by_id($request, $id);
        Post::remove_cache($post->id);
        if ($post->status != "private") {
            Log::add($request, "info", "Post edited");
        }
        if (isset($request->categories)) {
            $post->categories()->sync($request->categories);
        } else {
            $post->categories()->sync(array());
        }
        if (isset($request->tags)) {
            $post->tags()->sync($request->tags);
        } else {
            $post->tags()->sync(array());
        }

        Session::flash('success', __('blublog.contentupdate'));
        return redirect()->route('blublog.posts.show', $post->id);
    }
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        BlublogUser::check_access('delete', $post);
        $views = Rate::where([
            ['post_id', '=', $post->id],
        ])->get();
        foreach ($views as $view) {
            $view->delete();
        }

        if (!Post::img_used_by_other_post($id)) {
            if ($post->img != "no-img.png") {
                $path = 'posts/' . $post->img;
                $file = File::where([
                    ['filename', '=', $path],
                ])->first();
                if ($file) {
                    $file->delete();
                }

                if (!Post::delete_post_imgs($post->img)) {
                    Session::flash('error', __('blublog.error_removing'));
                }
            }
        }
        $post->categories()->detach();
        $post->tags()->detach();
        $comments = Comment::where([
            ['commentable_id', '=', $post->id],
        ])->get();
        foreach ($comments as $comments) {
            $comments->delete();
        }
        Post::remove_cache($post->id);
        $post->delete();

        Log::add($post, "info", "Post deleted");
        Session::flash('success', __('blublog.contentdelete'));
        return redirect()->route('blublog.posts.index');
    }
}
