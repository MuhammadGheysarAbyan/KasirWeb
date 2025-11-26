<?php
session_start();
if(!isset($_SESSION['id'])){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/db.php");

if(isset($_GET['id'])){
    $transaksi_id = (int)$_GET['id'];
    
    // Get transaction data
    $transaksi = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT t.*, u.username, u.nama as nama_kasir
        FROM transaksi t 
        JOIN users u ON t.kasir_id = u.id 
        WHERE t.id = '$transaksi_id'
    "));
    
    // Get transaction details
    $detail_transaksi = [];
    $query_detail = mysqli_query($conn, "
        SELECT dt.*, p.nama_produk, p.kode
        FROM detail_transaksi dt
        JOIN produk p ON dt.produk_id = p.id
        WHERE dt.transaksi_id = '$transaksi_id'
    ");
    while($row = mysqli_fetch_assoc($query_detail)){
        $detail_transaksi[] = $row;
    }
    
    // Get store settings
    $settings = [];
    $query_settings = mysqli_query($conn, "SELECT * FROM settings");
    while($row = mysqli_fetch_assoc($query_settings)){
        $settings[$row['nama_setting']] = $row['isi_setting'];
    }
} else {
    header("Location: transaksi.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi #<?= $transaksi_id ?></title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            font-size: 12px; 
            margin: 0; 
            padding: 10px;
            background: white;
        }
        .struk-container { 
            max-width: 300px; 
            margin: 0 auto;
            border: 1px dashed #ccc;
            padding: 15px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .divider { 
            border-top: 1px dashed #000; 
            margin: 10px 0; 
        }
        .item-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 3px;
        }
        .footer { 
            margin-top: 15px; 
            font-size: 10px; 
            text-align: center;
        }
        @media print {
            body { margin: 0; padding: 0; }
            .struk-container { border: none; padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="struk-container">
        <div class="text-center">
            <h3 style="margin: 0; font-size: 16px;"><?= $settings['nama_toko'] ?? 'Kasir Computer' ?></h3>
            <p style="margin: 2px 0; font-size: 10px;"><?= $settings['alamat_toko'] ?? '' ?></p>
            <p style="margin: 2px 0; font-size: 10px;">Telp: <?= $settings['telepon_toko'] ?? '' ?></p>
        </div>
        
        <div class="divider"></div>
        
        <div class="item-row">
            <span>No. Transaksi:</span>
            <span>#<?= $transaksi_id ?></span>
        </div>
        <div class="item-row">
            <span>Tanggal:</span>
            <span><?= date('d/m/Y H:i', strtotime($transaksi['tanggal'] . ' ' . $transaksi['waktu'])) ?></span>
        </div>
        <div class="item-row">
            <span>Kasir:</span>
            <span><?= htmlspecialchars($transaksi['nama_kasir']) ?></span>
        </div>
        
        <div class="divider"></div>
        
        <!-- Items -->
        <?php foreach($detail_transaksi as $item): ?>
        <div style="margin-bottom: 5px;">
            <div class="text-bold"><?= htmlspecialchars($item['nama_produk']) ?></div>
            <div class="item-row">
                <span><?= $item['qty'] ?> x Rp <?= number_format($item['harga'], 0, ',', '.') ?></span>
                <span>Rp <?= number_format($item['qty'] * $item['harga'], 0, ',', '.') ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="divider"></div>
        
        <div class="item-row text-bold">
            <span>TOTAL:</span>
            <span>Rp <?= number_format($transaksi['total'], 0, ',', '.') ?></span>
        </div>
        
        <div class="divider"></div>
        
        <div class="footer">
            <p><?= $settings['footer_struk'] ?? 'Terima kasih atas kunjungan Anda' ?></p>
            <p><?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>
    
    <div class="no-print text-center" style="margin-top: 20px;">
        <button onclick="window.print()" class="btn btn-primary">Print Struk</button>
        <button onclick="window.close()" class="btn btn-secondary">Tutup</button>
    </div>
    
    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>