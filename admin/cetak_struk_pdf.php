<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'admin'){
    die("Unauthorized");
}

include("../config/db.php");

$transaksi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($transaksi_id <= 0) {
    die("Invalid ID");
}

// Ambil data transaksi
$transaksi_query = mysqli_query($conn, "
    SELECT t.*, u.username as kasir 
    FROM transaksi t 
    LEFT JOIN users u ON t.kasir_id = u.id 
    WHERE t.id = $transaksi_id
");

if(mysqli_num_rows($transaksi_query) == 0) {
    die("Transaksi tidak ditemukan");
}

$transaksi = mysqli_fetch_assoc($transaksi_query);

// Ambil detail transaksi
$detail_query = mysqli_query($conn, "
    SELECT dt.*, p.nama_produk, (dt.qty * dt.harga) as subtotal
    FROM detail_transaksi dt 
    JOIN produk p ON dt.produk_id = p.id 
    WHERE dt.transaksi_id = $transaksi_id
");

$items = [];
while($row = mysqli_fetch_assoc($detail_query)) {
    $items[] = $row;
}

$uang_bayar = $transaksi['total'];
// Hitung kembalian jika data tersedia, jika tidak 0
$kembalian = isset($transaksi['bayar']) ? $transaksi['bayar'] - $transaksi['total'] : 0;
// Note: struktur tabel mungkin beda, sesuaikan field jika perlu. Di kasir ada uang_bayar di struk object dr json.
// Tapi di database tabel transaksi fieldnya apa?
// Cek riwayat.php: t.total, t.bayar, t.kembalian (jika ada column itu).
// Default ke 0 jika tdk ada info bayar.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Struk PDF</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            padding-top: 20px;
        }
        #struk-content {
            width: 80mm;
            background: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .flex-between { display: flex; justify-content: space-between; }
        .bold { font-weight: bold; }
        .mb-5 { margin-bottom: 5px; }
        .mb-10 { margin-bottom: 10px; }
        hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
        hr.solid { border-top: 2px solid #000; }
    </style>
</head>
<body>

    <div id="struk-content">
        <div class="text-center mb-10">
            <h3 style="margin: 0 0 5px 0;">Kasir Computer</h3>
            <p style="margin: 2px 0; font-size: 10px;">Jl. Contoh No. 123</p>
            <p style="margin: 2px 0; font-size: 10px;">Telp: (021) 123-4567</p>
            <hr class="solid">
        </div>

        <div class="mb-10">
            <p style="margin: 2px 0;"><strong>No:</strong> <?= $transaksi['kode_transaksi'] ?? 'TRX-'.$transaksi_id ?></p>
            <p style="margin: 2px 0;"><strong>Kasir:</strong> <?= $transaksi['kasir'] ?? 'Kasir' ?></p>
            <p style="margin: 2px 0;"><strong>Tanggal:</strong> <?= $transaksi['tanggal'] ?> <?= date('H:i', strtotime($transaksi['waktu'])) ?></p>
            <hr>
        </div>

        <div class="mb-10">
            <?php foreach($items as $item): ?>
                <div class="mb-5">
                    <div class="bold"><?= $item['nama_produk'] ?></div>
                    <div class="flex-between">
                        <span><?= $item['qty'] ?> x Rp <?= number_format($item['harga'],0,',','.') ?></span>
                        <span>Rp <?= number_format($item['subtotal'],0,',','.') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr class="solid">

        <div class="mb-10">
            <div class="flex-between mb-5 bold">
                <span>Total:</span>
                <span>Rp <?= number_format($transaksi['total'],0,',','.') ?></span>
            </div>
            <!-- Info bayar kembalian kalau ada di DB bisa ditampilkan, sementara hidden atau default -->
        </div>

        <hr class="solid">

        <div class="text-center" style="margin-top: 15px;">
            <p style="margin: 5px 0;">Terima kasih atas kunjungan Anda</p>
            <p style="margin: 2px 0; font-size: 10px;">www.kasircomputer.com</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            const element = document.getElementById('struk-content');
            const opt = {
                margin: 5,
                filename: 'Struk_<?= $transaksi['kode_transaksi'] ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: [80, 297], orientation: 'portrait' } 
            };

            // Generate PDF
            html2pdf().set(opt).from(element).save().then(function() {
                setTimeout(function() {
                    window.close();
                }, 3000);
            });
        };
    </script>
</body>
</html>
