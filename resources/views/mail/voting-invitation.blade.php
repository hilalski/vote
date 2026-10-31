<!DOCTYPE html>
<html lang="id"><body style="font-family: Arial, sans-serif; color: #263555; line-height: 1.6;">
<p>Halo {{ $user->name }},</p>
<p>Anda diundang untuk mengikuti pemilihan dua putaran.</p>
<p><strong>Link voting:</strong> <a href="{{ $votingUrl }}">{{ $votingUrl }}</a><br><strong>Token pribadi Anda:</strong> <code style="word-break: break-all;">{{ $token }}</code></p>
<p>Cara memilih:</p><ol><li>Buka link voting di atas.</li><li>Masukkan token pribadi Anda.</li><li>Pilih satu kandidat, lalu konfirmasi pilihan Anda.</li></ol>
<p>Pada Putaran 1, Anda dapat memilih semua peserta, termasuk diri sendiri. Pada Putaran 2, Anda dapat memilih satu dari tiga finalis. Anda tetap dapat mengikuti Putaran 2 meskipun tidak memilih di Putaran 1.</p>
<p><strong>Jangan membagikan token ini kepada orang lain.</strong></p>
<p>Salam,<br>Panitia Pemilihan</p>
</body></html>
