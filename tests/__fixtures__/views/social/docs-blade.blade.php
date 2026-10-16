<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    {!! Statamic::tag('social_image:font')->params(['family' => 'Fraunces', 'weight' => 600]) !!}
    body { width: {{ $social_layout['width'] }}px; }
</style>
</head>
<body>
    @if ($cover_image->value())
        <img class="cover" src="{{ Statamic::tag('social_image:embed')->params(['src' => $cover_image, 'width' => 320, 'height' => 480]) }}" alt="">
    @endif
    <h1 data-fit="88" data-fit-min="40" data-fit-lines="3">{{ $title }}</h1>
    <p>{{ $date->format('j F Y') }}</p>
</body>
</html>
