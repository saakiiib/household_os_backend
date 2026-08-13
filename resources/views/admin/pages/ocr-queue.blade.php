@extends('admin.pages.master')
@section('title', 'OCR & Document Intelligence')
@section('content')
@include('admin.pages.module', [
    'title' => 'OCR & Document Intelligence',
    'icon' => 'ri-scan-line',
    'desc' => 'Queue and results for document OCR, classification and data extraction.',
])
@endsection
