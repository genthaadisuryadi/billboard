const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const path = require('path');

const app = express();
const server = http.createServer(app);
const io = new Server(server);

// Folder public sebagai tempat file HTML dan gambar
app.use(express.static(path.join(__dirname, 'public')));
// Perbesar limit JSON untuk menampung gambar resolusi tinggi dari HP
app.use(express.json({ limit: '50mb' }));

io.on('connection', (socket) => {
    console.log('Perangkat terhubung:', socket.id);

    // Menerima foto mentah (tanpa potong AI) dari HP audiens
    socket.on('kirim_raw_foto', (data) => {
        console.log('Foto mentah diterima dari HP! Meneruskan ke Laptop untuk diproses AI...');
        // Kirim perintah ke layar laptop untuk memotong background
        socket.broadcast.emit('proses_ai_di_laptop', data);
    });
});

const PORT = 3000;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`=========================================`);
    console.log(`🚀 SERVER UCIC DOOH BERJALAN!`);
    console.log(`💻 Buka di Laptop: http://localhost:${PORT}/display.html`);
    console.log(`=========================================`);
});