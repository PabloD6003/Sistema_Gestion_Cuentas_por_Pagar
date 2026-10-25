<section class="section">
    <div class="columns is-centered">
        <div class="column is-6-desktop is-10-tablet">
            <div class="box has-text-centered app-error-box">
                <p class="app-error-code"><?= e($status ?? 500) ?></p>
                <h2 class="title is-4"><?= ($status ?? 500) === 404 ? 'No encontramos esta página' : 'No se pudo completar la operación' ?></h2>
                <p class="subtitle is-6 has-text-grey"><?= e($message ?? 'Ocurrió un error inesperado.') ?></p>
                <div class="buttons is-centered mt-5">
                    <?php if (\App\Auth::check()): ?>
                        <a class="button is-primary" href="/dashboard">Volver al panel</a>
                        <a class="button is-light" href="/cuentas">Ir a cuentas</a>
                    <?php else: ?>
                        <a class="button is-primary" href="/login">Iniciar sesión</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
