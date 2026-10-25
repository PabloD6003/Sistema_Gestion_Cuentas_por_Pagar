<section class="login-hero">
    <div class="login-card box">
        <div class="has-text-centered mb-5">
            <span class="login-mark">CxP</span>
            <h1 class="title is-4 mt-3">Cuentas por Pagar</h1>
            <p class="subtitle is-6 has-text-grey">Acceso al sistema de gestión financiera</p>
        </div>

        <?php if (!empty($loggedOut)): ?>
            <div class="notification is-success is-light">Su sesión se cerró correctamente.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="notification is-danger is-light"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" autocomplete="on">
            <?= csrf_field() ?>
            <div class="field">
                <label class="label" for="nombre_usuario">Usuario</label>
                <div class="control">
                    <input class="input is-medium" id="nombre_usuario" name="nombre_usuario" type="text"
                           value="<?= e($username ?? '') ?>" placeholder="admin" required autofocus>
                </div>
            </div>

            <div class="field">
                <label class="label" for="contrasena">Contraseña</label>
                <div class="control">
                    <input class="input is-medium" id="contrasena" name="contrasena" type="password"
                           placeholder="••••••••" required>
                </div>
            </div>

            <button class="button is-primary is-medium is-fullwidth mt-5" type="submit">Ingresar</button>
        </form>

        <div class="login-demo">
            <p class="has-text-weight-semibold mb-2">Usuarios de demostración</p>
            <div class="login-demo-grid">
                <span><strong>admin</strong> / Admin1234</span>
                <span><strong>tesorero</strong> / Tesorero123</span>
                <span><strong>contador</strong> / Contador123</span>
            </div>
            <p class="is-size-7 has-text-grey mt-2">El administrador y el tesorero pueden aprobar pagos. El contador registra facturas y pagos.</p>
        </div>
    </div>
</section>
