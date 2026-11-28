@extends('site.community._layout')
@section('ctitle', \App\Support\Community\Text::excerpt($post->body, 60))
@section('main')
    <a href="{{ route('community.home') }}" class="text-sm text-nova-700 hover:underline">← Zur Pinnwand</a>
    <div class="mt-3">@include('site.community._post', ['post' => $post, 'showAll' => true])</div>
@endsection
