@extends('blublog::panel.main')

@section('content')
<div class="row">
    @include('blublog::panel.partials.colums', ['title' => __('panel.posts_this'),'val'=>$this_month_posts,'color'=>"primary",'icon'=>"newspaper"])
    @include('blublog::panel.partials.colums', ['title' => __('panel.posts_last'),'val'=>$last_month_posts,'color'=>"success",'icon'=>"newspaper"])
    @include('blublog::panel.partials.colums', ['title' => __('panel.posts_total'),'val'=>$totalposts,'color'=>"info",'icon'=>"newspaper"])
    @include('blublog::panel.partials.colums', ['title' => __('panel.comments'),'val'=>$totalcomments,'color'=>"warning",'icon'=>"comments"])
</div>
@if ($notpubliccomments != 0)
<div class="alert alert-warning" role="alert">
    ({{$notpubliccomments}}) {{__('panel.comments_waiting')}}
</div>
@endif
@include('blublog::panel.partials.continue_edit')
@endsection
