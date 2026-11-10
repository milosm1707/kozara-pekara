<?php
?>
</main><!-- /.main-content -->

<footer class="footer">
    <div class="footer-container">
        <div class="footer-brand">
            <a href="/index.php" class="footer-logo">
                <span class="footer-logo-icon">
                    <!-- Fiksna visina i u footeru da logo ne raste preko cijelog ekrana -->
                    <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="footer-logo-img" height="40">
                </span>
                Kozara
            </a>
            <p class="footer-tagline">Svježe pečeno, direktno do vas.</p>
        </div>

        <div class="footer-links">
            <div class="footer-col">
                <h4>Meni</h4>
                <ul>
                    <?php if (isLoggedIn() && isPekar()): ?>
                        <li><a href="/profile.php">Moj profil</a></li>
                        <li><a href="/pekar/orders.php">Narudžbe</a></li>
                    <?php else: ?>
                        <li><a href="/index.php">Svi proizvodi</a></li>
                        <li><a href="/cart.php">Korpa</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/orders.php">Moje narudžbe</a></li>
                        <?php else: ?>
                            <li><a href="/login.php">Prijava</a></li>
                            <li><a href="/register.php">Registracija</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Kontakt</h4>
                <ul>
                    <li><a href="/about.php">📍 O nama</a></li>
                    <li><a href="/contact.php">✉ Kontakt</a></li>
                    <li><a href="tel:052233333">📞 052 233 333</a></li>
                    <li>🕐 <?= e(getOrderingHoursLabel()) ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© <?= date('Y') ?> Kozara. Sva prava zadržana.</p>
        <p>Plaćanje pouzećem · Dostava na adresu</p>
        <p class="footer-dev-credit">Izrada sajta i aplikacija — <a href="mailto:mmwebsites7@gmail.com">mmwebsites7@gmail.com</a></p>
    </div>
</footer>

<script src="<?= asset_url('/assets/js/main.js') . '?v=2' ?>"></script>
<script src="<?= asset_url('/assets/js/cart.js') . '?v=2' ?>"></script>
</body>
</html>
