@extends('layouts.admin')

@php $role = auth()->user()->panel; @endphp

@section('title', $role === 'guru' ? 'Jadwal Mengajar' : 'Jadwal Pelajaran')

@section('content')
    <x-admin.page-header :title="$role === 'guru' ? 'Jadwal Mengajar' : 'Jadwal Pelajaran'" subtitle="{{ $akademik['label'] }}" />
    <x-admin.jadwal-grid :url="route($role.'/jadwal.grid')" :tampilkan-kelas="$role === 'guru'" :tampilkan-guru="$role === 'siswa'" />
@endsection
