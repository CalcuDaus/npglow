document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Konsultasi Button Logic
    const konsultasiBtns = document.querySelectorAll('.btn-konsultasi');
    
    konsultasiBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            // Simulasi pengecekan status pembelian user
            // Untuk mockup ini, kita asumsikan user belum login / belum pernah beli
            const hasPurchased = false; 

            if (!hasPurchased) {
                Swal.fire({
                    icon: 'info',
                    title: 'Oops...',
                    text: 'Konsultasi hanya untuk pengguna yang sudah pernah membeli produk NPGLOW minimal 1x.',
                    confirmButtonText: 'Beli Produk Sekarang',
                    confirmButtonColor: '#3ca6f2',
                    showCancelButton: true,
                    cancelButtonText: 'Tutup'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Arahkan ke marketplace section
                        document.getElementById('marketplace').scrollIntoView({ behavior: 'smooth' });
                    }
                });
            } else {
                // Flow jika sudah beli (bisa redirect ke chat room)
                Swal.fire({
                    icon: 'success',
                    title: 'Memuat Chat...',
                    text: 'Mengarahkan ke ruangan konsultasi...',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        });
    });
});
