<?php

namespace Tests\Feature;

use App\Mail\ServerErrorOccurred;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ErrorNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->app['env'] = 'production';
    }

    public function test_reported_error_is_mailed_to_error_recipient_in_production(): void
    {
        Setting::put('error_notification_email', 'fouten@example.com');

        report(new RuntimeException('Database onbereikbaar'));

        Mail::assertSent(ServerErrorOccurred::class, fn (ServerErrorOccurred $mail): bool => $mail->hasTo('fouten@example.com')
            && $mail->exceptionMessage === 'Database onbereikbaar');
    }

    public function test_error_mail_renders_error_details(): void
    {
        $mail = new ServerErrorOccurred(RuntimeException::class, 'Database onbereikbaar', 'app/Foo.php:12', '#0 {main}', 'https://example.com/units/', 'GET', '127.0.0.1');

        $mail->assertSeeInHtml('Database onbereikbaar');
        $mail->assertSeeInHtml('https://example.com/units/');
        $mail->assertHasSubject('Serverfout op '.config('app.name').': RuntimeException – Database onbereikbaar');
    }

    public function test_error_recipient_falls_back_to_form_recipient(): void
    {
        Setting::put('notification_email', 'formulieren@example.com');

        report(new RuntimeException('Boom'));

        Mail::assertSent(ServerErrorOccurred::class, fn (ServerErrorOccurred $mail): bool => $mail->hasTo('formulieren@example.com'));
    }

    public function test_same_error_is_mailed_only_once_within_throttle_window(): void
    {
        foreach (range(1, 3) as $attempt) {
            report(new RuntimeException('Herhaalde fout'));
        }

        Mail::assertSentCount(1);
    }

    public function test_no_mail_outside_production(): void
    {
        $this->app['env'] = 'local';

        report(new RuntimeException('Lokale fout'));

        Mail::assertNothingSent();
    }

    public function test_not_found_pages_do_not_send_a_mail(): void
    {
        $this->get('/bestaat-niet/')->assertNotFound();

        Mail::assertNothingSent();
    }
}
