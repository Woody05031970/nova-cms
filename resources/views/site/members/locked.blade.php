@extends('site.layout')
@section('title', $page->title.' – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')@include('site.members._login-form')@endsection
@section('content')@include('site.members._shell', ['heading' => $page->title, 'lead' => 'Diese Seite ist nur für Mitglieder. Bitte melden Sie sich an.'])@endsection
