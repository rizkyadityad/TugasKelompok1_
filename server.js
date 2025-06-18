// Contoh: server.js (Node.js dengan Express)
const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors'); // Untuk permintaan lintas-origin

const app = express();
const PORT = 3000;

// Middleware
app.use(bodyParser.json()); // Untuk mengurai body permintaan JSON
app.use(cors()); // Izinkan permintaan dari asal frontend Anda

// Dalam aplikasi nyata, Anda akan terhubung ke database di sini
// Untuk demonstrasi, kita akan menggunakan array sederhana dalam memori (BUKAN untuk produksi)
let transactionsDb = [];
// (Dalam produksi, ini akan menjadi koneksi database dan logika query Anda)

// API untuk mendapatkan semua transaksi (dengan filter)
app.get('/api/transactions', (req, res) => {
    let filtered = [...transactionsDb]; // Mulai dengan semua transaksi

    // Terapkan filter dari parameter query
    const { dateFrom, dateTo, status, paymentMethod } = req.query;

    if (dateFrom) {
        const fromDate = new Date(dateFrom);
        filtered = filtered.filter(tx => new Date(tx.timestamp) >= fromDate);
    }
    if (dateTo) {
        // Tambahkan 23:59:59 ke dateTo untuk menyertakan seluruh hari
        const toDate = new Date(dateTo + 'T23:59:59');
        filtered = filtered.filter(tx => new Date(tx.timestamp) <= toDate);
    }
    if (status) {
        filtered = filtered.filter(tx => tx.status === status);
    }
    if (paymentMethod) {
        filtered = filtered.filter(tx => tx.paymentMethod === paymentMethod);
    }

    // Urutkan berdasarkan tanggal (terbaru lebih dulu)
    filtered.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    res.json(filtered);
});

// API untuk menambahkan transaksi baru
app.post('/api/transactions', (req, res) => {
    const newTransaction = req.body;
    // Dalam aplikasi nyata, Anda akan menambahkan validasi sisi server di sini
    // Dan menghasilkan ID unik jika belum disediakan oleh klien
    newTransaction.id = 'TXN-' + Date.now() + Math.floor(Math.random() * 1000); // ID yang dihasilkan server
    newTransaction.timestamp = new Date().toISOString(); // Timestamp yang dihasilkan server
    transactionsDb.push(newTransaction);
    // Dalam database nyata, Anda akan menyimpannya dan mendapatkan ID/objek yang dimasukkan kembali
    res.status(201).json(newTransaction); // Tanggapi dengan transaksi yang dibuat
});

// API untuk mendapatkan satu transaksi berdasarkan ID
app.get('/api/transactions/:id', (req, res) => {
    const transactionId = req.params.id;
    const transaction = transactionsDb.find(tx => tx.id === transactionId);
    if (transaction) {
        res.json(transaction);
    } else {
        res.status(404).send('Transaction not found');
    }
});

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
    // Opsional: Isi dengan beberapa data sampel awal saat server dimulai (untuk pengujian)
    // Anda mungkin memuat ini dari file JSON atau database mock sederhana dalam pengembangan
    // Anda *tidak* boleh melakukan ini dalam produksi atau menggunakan data sampel nyata
    if (transactionsDb.length === 0) {
        // Di sini Anda akan secara terprogram menambahkan beberapa transaksi sampel ke transactionsDb
        // Mirip dengan fungsi createSampleTransactions di HTML Anda, tetapi di server
        console.log("Adding sample data to in-memory DB...");
        const sampleCustomers = [
            { name: 'John Doe', phone: '081234567890', email: 'john@example.com', address: 'Jl. Contoh No. 123, Jakarta' },
            { name: 'Jane Smith', phone: '081234567891', email: 'jane@example.com', address: 'Jl. Merdeka No. 456, Bandung' },
            { name: 'Ahmad Rahman', phone: '081234567892', email: 'ahmad@example.com', address: 'Jl. Sudirman No. 789, Surabaya' }
        ];

        const sampleItems = [
            { name: 'Wedang Ronde Original', price: 15000 },
            { name: 'Wedang Jahe', price: 12000 },
            { name: 'Ketan Hitam', price: 10000 },
            { name: 'Wedang Bajigur', price: 14000 },
            { name: 'Es Dawet', price: 13000 },
            { name: 'Bubur Sumsum', price: 11000 }
        ];

        const paymentMethods = ['cash', 'transfer', 'ewallet', 'qris'];
        const statuses = ['confirmed', 'pending', 'cancelled'];

        for (let i = 0; i < 15; i++) {
            const daysAgo = Math.floor(Math.random() * 30);
            const transactionDate = new Date();
            transactionDate.setDate(transactionDate.getDate() - daysAgo);

            const customer = sampleCustomers[Math.floor(Math.random() * sampleCustomers.length)];
            const numItems = Math.floor(Math.random() * 4) + 1;
            const items = [];
            let subtotal = 0;

            for (let j = 0; j < numItems; j++) {
                const item = sampleItems[Math.floor(Math.random() * sampleItems.length)];
                const quantity = Math.floor(Math.random() * 3) + 1;
                items.push({
                    name: item.name,
                    price: item.price,
                    quantity: quantity
                });
                subtotal += item.price * quantity;
            }

            const tax = Math.floor(subtotal * 0.1);
            const shipping = Math.floor(Math.random() * 10000) + 5000;
            const total = subtotal + tax + shipping;

            const transaction = {
                id: 'TXN-' + (Date.now() + i),
                timestamp: transactionDate.toISOString(),
                customer: {
                    name: customer.name,
                    phone: customer.phone,
                    email: customer.email,
                    address: customer.address,
                    notes: Math.random() > 0.5 ? 'Mohon hangatkan' : ''
                },
                items: items,
                subtotal: subtotal,
                tax: tax,
                shipping: shipping,
                total: total,
                paymentMethod: paymentMethods[Math.floor(Math.random() * paymentMethods.length)],
                status: statuses[Math.floor(Math.random() * statuses.length)]
            };
            transactionsDb.push(transaction);
        }
        transactionsDb.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
    }
});