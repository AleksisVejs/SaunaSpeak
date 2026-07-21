<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Win-back outreach is sent by hand from webmail, not by the app, so nothing
 * recorded who had already been written to. Without that record the admin's
 * lapsed-learner list re-offers the same people every time it is opened, and
 * the second mail lands on someone who ignored the first - the fastest way to
 * turn a win-back into a spam complaint.
 *
 * One nullable timestamp on the user is enough: these are one-off personal
 * mails, and "when did I last write to them" is the only question the list
 * needs to answer.
 */
return new class extends Migration
{
    /** The 2026-07-20 01:58 win-back, sent Bcc from webmail before this existed. */
    private const ALREADY_EMAILED = [
        'surinderkaur670@gmail.com',
        'eninike8@gmail.com',
        '05egayrowena21@gmail.com',
        'wagon_wheel1111@yahoo.com',
        'leinabrenn@gmail.com',
        'breanndan.o.nuallain@gmail.com',
        'ulusoyca@gmail.com',
        'ossiruskelaa@gmail.com',
        'lamson1207@gmail.com',
        'foxguinness@gmail.com',
        'xtaabiola@gmail.com',
        'tatjana_leininger@yahoo.no',
        'mfbjerring@gmail.com',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('outreach_emailed_at')->nullable()->after('review_emails');
        });

        // Backfill that first send, so the list opens already excluding them
        // instead of handing back thirteen people who were mailed yesterday.
        DB::table('users')
            ->whereIn('email', self::ALREADY_EMAILED)
            ->update(['outreach_emailed_at' => '2026-07-20 01:58:00']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('outreach_emailed_at');
        });
    }
};
