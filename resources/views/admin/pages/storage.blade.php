@extends('admin.pages.master')
@section('title', 'Storage Explorer')
@section('content')
@include('admin.pages.module', [
    'title' => 'Storage Explorer',
    'icon' => 'ri-hard-drive-2-line',
    'desc' => 'Browse and manage household file storage, quotas and usage.',
])
@endsection
