{{--
    Opbouw overgenomen van components/email-layout.blade.php in de inlogomgeving:
    één kaart met merkregel, inhoud en footer. Styling staat in themes/mails.css.
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ \App\Models\Setting::get('site_name', config('app.name')) }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 620px) {
.wrapper-cell {
padding: 16px 8px !important;
}

.inner-body {
width: 100% !important;
max-width: 100% !important;
}

.header,
.content-cell,
.footer {
padding-left: 18px !important;
padding-right: 18px !important;
}

.button {
display: block !important;
text-align: center !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="wrapper-cell" align="center">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
