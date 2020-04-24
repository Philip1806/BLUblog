@extends('blublog::blublog.main')
@section('title') {{$post->seo_title}} @endsection
@section('meta')
<!-- Open Graph / Facebook -->
<meta name="og:title" property="og:title" content="{{$post->seo_title}}">
<meta name="og:description" property="og:description" content="{{$post->seo_descr}}">
<meta name="og:image" property="og:image" content="{{url('/uploads/posts/')}}/{{$post->img}}" />
<meta name="og:type" property="og:type" content="article" >
<meta name="og:published_time" property="og:published_time" content="{{$post->created_at}}" >
<meta name="og:article:section" property="og:article:section" content="{{$post->categories[0]->title}}" >
@if (isset($post->tags[0]->id))
@foreach ( $post->tags as $tag)
<meta name="og:article:tag" property="og:article:tag" content="{{ $tag->title }}" >
@endforeach
@endif
<meta name="og:url" property="og:url" content="{{ url('/') }}/posts/{{ $post->slug}}" />
<meta name="og:locale" property="og:locale" content="en_EN" />
<meta name="og:site_name" property="og:site_name" content="{!!blublog_setting('site_name')!!}" >
<meta name="robots" content="index, follow">
@endsection
@section('jumbotron')
<style>
.jumbotron {
  background-image: linear-gradient( rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5) ), url("{{url('/uploads/posts/')}}/{{$post->img}}");
  background-size: cover;
  background-repeat: no-repeat;
  border-color: {{$post->categories[0]->colorcode}};
  border-bottom-style: solid;
  border-width: 5px;
}
.display-comment .display-comment {
        margin-left: 40px
}
</style>

<div class="jumbotron" style="max-height: 500px;padding:143px;">
    <div class="container-fluid text-white">
    <h2>{{$post->title}}</h2>
    <span class="badge badge-{{$post->categories[0]->id}}">{{$post->categories[0]->title}}</span>

    </div>
</div>
@endsection
@section('content')

<div class="col-lg-9">
    {!! $post->content !!}
    {!! $post->STARS_HTML !!}
<br><small>Posted by <a href="{{$post->author_url}}"> {{ $post->user->name }}</a> on {{ $post->date }}</small>
    @foreach ($post->tags as $tag)
        <a href="{{ route('blublog.front.tag_show', $tag->slug) }}"><span class="badge badge-pill badge-dark">{{$tag->title}}</span></a>
    @endforeach
        <hr>
    @if ($post->comments)
        @include('blublog::comments._comments')
    @else
        <div class="text-center"><small>{{__('panel.comments_disabled')}}</small></div>
    @endif
</div>

<div class="col-lg-3">
    @include('blublog::blublog.parts._sidebar')
</div>


<script>
function rating(){
    let rating_info = document.getElementById("rating_info");
rating_info.innerHTML = "Searching for ";
}
function clear_stars(){
    for (let i = 1; i<6; i++){
        let star = document.getElementById(i + "_star");
        star.style = "";
    }
}
function set_ratingto(star_id){
    clear_stars();
    let rating_info = document.getElementById("rating_info");
    for (let i = 1; i<6; i++){
        let star_now = i + "_star";
        let star = document.getElementById(star_now);
        star.style = "color:blue;";
        if(star_now == star_id){
            send_rating(i);
            break;
        }
    }

}
function send_rating(selected_star){
    let rating_info = document.getElementById("rating_info");
    let errormsg =  "<b>Rating refused or there was a error. Admin is notified.</b>";

    $.ajax({

    type:'POST',

    url:"{{ url('/blublog/set_rating') }}",

    data:{"_token": "{{ csrf_token() }}",post:"{{$post->id}}",star:selected_star},

    success:function(data){
        if(data){
            rating_info.innerHTML = data;
        } else {
            rating_info.innerHTML = errormsg;
        }
    },
    error: function (xhr, ajaxOptions, thrownError) {
        rating_info.innerHTML = errormsg;
    }
    });
}
</script>
@endsection
