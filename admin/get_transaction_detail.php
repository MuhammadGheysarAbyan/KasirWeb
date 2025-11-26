<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/db.php");

if(isset($_GET['id'])) {
    $transaksi_id = (int)$_GET['id'];
    
    // Get transaction info
    $transaksi_info = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT t.*, u.username, u.nama as nama_kasir
        FROM transaksi t 
        JOIN users u ON t.kasir_id = u.id 
        WHERE t.id = '$transaksi_id'
    "));
    
    // Get transaction details
    $detail_transaksi = [];
    $query_detail = mysqli_query($conn, "
        SELECT dt.*, p.nama_produk, p.kode, p.foto
        FROM detail_transaksi dt
        JOIN produk p ON dt.produk_id = p.id
        WHERE dt.transaksi_id = '$transaksi_id'
    ");
    while($row = mysqli_fetch_assoc($query_detail)){
        $detail_transaksi[] = $row;
    }
    
    if($transaksi_info) {
        echo '
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="detail-section">
                    <h6><i class="fa fa-info-circle text-primary"></i> Informasi Transaksi</h6>
                    <table class="table table-sm table-borderless">
                        <tr><td width="140"><strong>Kode Transaksi</strong></td><td><code>'.htmlspecialchars($transaksi_info['kode_transaksi']).'</code></td></tr>
                        <tr><td><strong>Tanggal</strong></td><td>'.date('d/m/Y H:i:s', strtotime($transaksi_info['tanggal'].' '.$transaksi_info['waktu'])).'</td></tr>
                        <tr><td><strong>Kasir</strong></td><td>'.htmlspecialchars($transaksi_info['nama_kasir']).' ('.htmlspecialchars($transaksi_info['username']).')</td></tr>
                        <tr><td><strong>Status</strong></td><td>'.getStatusBadge($transaksi_info['status']).'</td></tr>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-section">
                    <h6><i class="fa fa-money-bill-wave text-success"></i> Informasi Pembayaran</h6>
                    <table class="table table-sm table-borderless">
                        <tr><td width="140"><strong>Total Item</strong></td><td>'.count($detail_transaksi).' item</td></tr>
                        <tr><td><strong>Total Bayar</strong></td><td><strong class="text-success">Rp '.number_format($transaksi_info['total'], 0, ',', '.').'</strong></td></tr>
                        <tr><td><strong>Status Bayar</strong></td><td><span class="badge bg-success">LUNAS</span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h6><i class="fa fa-shopping-cart text-info"></i> Detail Produk</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="60">Gambar</th>
                            <th>Produk</th>
                            <th>Kode</th>
                            <th width="80" class="text-center">Qty</th>
                            <th width="120" class="text-end">Harga</th>
                            <th width="120" class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach($detail_transaksi as $detail) {
            echo '
                        <tr>
                            <td>
                                <img src="../assets/img/produk/'.$detail['foto'].'" 
                                     class="product-image"
                                     onerror="this.src=\'../assets/img/default-product.jpg\'">
                            </td>
                            <td>'.htmlspecialchars($detail['nama_produk']).'</td>
                            <td><code>'.htmlspecialchars($detail['kode']).'</code></td>
                            <td class="text-center">'.$detail['qty'].'</td>
                            <td class="text-end">Rp '.number_format($detail['harga'], 0, ',', '.').'</td>
                            <td class="text-end"><strong>Rp '.number_format($detail['qty'] * $detail['harga'], 0, ',', '.').'</strong></td>
                        </tr>';
        }
        
        echo '
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end"><strong>Total:</strong></td>
                            <td class="text-end"><strong class="text-success">Rp '.number_format($transaksi_info['total'], 0, ',', '.').'</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>';
    } else {
        echo '<div class="alert alert-danger">Transaksi tidak ditemukan</div>';
    }
} else {
    echo '<div class="alert alert-danger">ID Transaksi tidak valid</div>';
}

function getStatusBadge($status) {
    $statusConfig = [
        'selesai' => ['class' => 'bg-success', 'icon' => 'fa-check-circle', 'text' => 'Selesai'],
        'batal' => ['class' => 'bg-danger', 'icon' => 'fa-times-circle', 'text' => 'Batal']
    ];
    
    if(isset($statusConfig[$status])) {
        $config = $statusConfig[$status];
        return '<span class="badge '.$config['class'].'"><i class="fa '.$config['icon'].' me-1"></i>'.$config['text'].'</span>';
    }
    
    return '<span class="badge bg-secondary">'.$status.'</span>';
}
?>