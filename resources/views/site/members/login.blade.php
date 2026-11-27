@extends('site.layout')
@section('title', 'Anmelden – '.$siteName)
@section('meta')<meta name="robots" content="noindex">@endsection
@section('form')@include('site.members._login-form')@endsection
@section('content')@include('site.members._shell', ['heading' => 'Anmelden'])@endsection
