package br.com.prospera.operacoes;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.graphics.Typeface;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.Vibrator;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

import java.io.IOException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

/**
 * O app do tablet: o sistema em tela cheia, sem a barra do navegador.
 * As telas vêm sempre do sistema; o app só cuida do que o navegador não fazia neste tablet.
 */
public class Principal extends Activity {
    // Endereço do sistema. O android/construir.sh troca este valor pelo da variável ENDERECO.
    static final String ENDERECO = "https://operacoes.fabbiol.tech/";
    static final String VERSAO = "1.1";

    private static final int ESCOLHER_FOTO = 1;
    private static final int ESPERA_MS = 10000;
    private static final int PAPEL = 0xfff5f3ed;
    private static final int TINTA = 0xff191b1b;
    private static final int CINZA = 0xff5e635d;
    private static final int OURO = 0xfff2b705;

    private final Handler relogio = new Handler(Looper.getMainLooper());
    private WebView web;
    private LinearLayout semConexao;
    private TextView situacao;
    private String paraAbrir = ENDERECO;
    private ValueCallback<Uri[]> fotoPendente;
    private boolean tentando;

    private final Runnable tentarDeNovo = new Runnable() {
        @Override
        public void run() {
            tentar();
        }
    };

    @Override
    protected void onCreate(Bundle salvo) {
        super.onCreate(salvo);
        FrameLayout raiz = new FrameLayout(this);
        web = new WebView(this);
        raiz.addView(web, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        semConexao = montarSemConexao();
        semConexao.setVisibility(View.GONE);
        raiz.addView(semConexao, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        setContentView(raiz);
        // O Android não oferece para guardar senhas digitadas no app: o tablet é de todos.
        semAutopreenchimento(raiz);
        semAutopreenchimento(web);
        configurar();
        if (salvo == null || web.restoreState(salvo) == null) {
            web.loadUrl(ENDERECO);
        }
    }

    private void configurar() {
        WebSettings ajustes = web.getSettings();
        ajustes.setJavaScriptEnabled(true);
        ajustes.setDomStorageEnabled(true);
        ajustes.setSaveFormData(false);
        ajustes.setAllowFileAccess(false);
        ajustes.setMediaPlaybackRequiresUserGesture(true);
        // O sistema reconhece o app por aqui (por exemplo, para não oferecer "baixe o app" dentro dele).
        ajustes.setUserAgentString(ajustes.getUserAgentString() + " ProsperaApp/" + VERSAO);

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(web, false);

        web.addJavascriptInterface(new Ponte(), "ProsperaApp");
        web.setWebViewClient(new Navegacao());
        web.setWebChromeClient(new Janela());
    }

    /** O que as telas do sistema podem pedir ao app. */
    public final class Ponte {
        /** Tela acesa enquanto a pessoa está com uma máquina; ao soltar, o tablet volta a apagar a tela. */
        @JavascriptInterface
        public void telaAcesa(final boolean acesa) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    if (acesa) {
                        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
                    } else {
                        getWindow().clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
                    }
                }
            });
        }

        @JavascriptInterface
        public String versao() {
            return VERSAO;
        }

        /**
         * Aviso do pátio (carga nova, alterada ou cancelada): o som de notificação do tablet e a vibração.
         * Pelo app toca mesmo sem a pessoa ter tocado na tela antes, o que o navegador não deixa.
         */
        @JavascriptInterface
        @SuppressWarnings("deprecation")
        public void avisar(final String tipo) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    try {
                        Uri som = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
                        Ringtone toque = som != null ? RingtoneManager.getRingtone(getApplicationContext(), som) : null;
                        if (toque != null) {
                            toque.play();
                        }
                    } catch (RuntimeException e) {
                        // Sem som de notificação configurado: fica a vibração e o aviso na tela.
                    }
                    Vibrator vibrador = (Vibrator) getSystemService(Context.VIBRATOR_SERVICE);
                    if (vibrador == null || !vibrador.hasVibrator()) {
                        return;
                    }
                    long[] padrao = "cancelada".equals(tipo)
                        ? new long[] {0, 700, 250, 700}
                        : new long[] {0, 400, 200, 400};
                    // O app é montado contra o Android 6; esta forma ainda vale no Android 14.
                    vibrador.vibrate(padrao, -1);
                }
            });
        }
    }

    private final class Navegacao extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView vista, String endereco) {
            return abrirFora(Uri.parse(endereco));
        }

        // Android 7 em diante chama esta.
        public boolean shouldOverrideUrlLoading(WebView vista, WebResourceRequest pedido) {
            return abrirFora(pedido.getUrl());
        }

        @Override
        public void onReceivedError(WebView vista, WebResourceRequest pedido, WebResourceError erro) {
            if (!pedido.isForMainFrame()) {
                return;
            }
            // Voltar para uma tela que veio de um formulário: abre o começo, sem reenviar nada.
            if (String.valueOf(erro.getDescription()).contains("ERR_CACHE_MISS")) {
                vista.loadUrl(ENDERECO);
                return;
            }
            mostrarSemConexao(pedido.getUrl().toString());
        }

        @Override
        public void onPageFinished(WebView vista, String endereco) {
            // A autorização do tablet e a sessão ficam guardadas mesmo se o app for fechado de repente.
            CookieManager.getInstance().flush();
        }
    }

    private final class Janela extends WebChromeClient {
        @Override
        public boolean onShowFileChooser(WebView vista, ValueCallback<Uri[]> retorno, FileChooserParams pedido) {
            if (fotoPendente != null) {
                fotoPendente.onReceiveValue(null);
            }
            fotoPendente = retorno;
            Intent escolher = new Intent(Intent.ACTION_GET_CONTENT);
            escolher.addCategory(Intent.CATEGORY_OPENABLE);
            escolher.setType("image/*");
            try {
                startActivityForResult(Intent.createChooser(escolher, "Escolher foto"), ESCOLHER_FOTO);
            } catch (ActivityNotFoundException e) {
                fotoPendente = null;
                retorno.onReceiveValue(null);
            }
            return true;
        }
    }

    /** Só o sistema abre dentro do app. Qualquer outro endereço vai para o navegador. */
    private boolean abrirFora(Uri uri) {
        Uri sistema = Uri.parse(ENDERECO);
        if ("https".equals(uri.getScheme()) && sistema.getHost() != null && sistema.getHost().equalsIgnoreCase(uri.getHost())) {
            return false;
        }
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (ActivityNotFoundException e) {
            // Nada que abra esse endereço: o toque é ignorado.
        }
        return true;
    }

    @Override
    protected void onActivityResult(int pedido, int resultado, Intent dados) {
        if (pedido == ESCOLHER_FOTO && fotoPendente != null) {
            fotoPendente.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(resultado, dados));
            fotoPendente = null;
            return;
        }
        super.onActivityResult(pedido, resultado, dados);
    }

    // ---- Sem conexão: só aparece se a tela não abre e o sistema ainda não tem a dele guardada no tablet. ----

    private LinearLayout montarSemConexao() {
        LinearLayout caixa = new LinearLayout(this);
        caixa.setOrientation(LinearLayout.VERTICAL);
        caixa.setGravity(Gravity.CENTER);
        caixa.setBackgroundColor(PAPEL);
        int margem = dp(24);
        caixa.setPadding(margem, margem, margem, margem);
        caixa.setClickable(true);

        caixa.addView(texto("Próspera", 20, TINTA, true));
        TextView titulo = texto("Sem conexão", 28, TINTA, true);
        titulo.setPadding(0, dp(24), 0, dp(12));
        caixa.addView(titulo);
        caixa.addView(texto("O tablet não está conseguindo falar com o sistema.", 17, TINTA, false));
        TextView dica = texto("Confira se o Wi-Fi está ligado e se o tablet está perto do roteador.", 17, CINZA, false);
        dica.setPadding(0, dp(8), 0, dp(20));
        caixa.addView(dica);
        situacao = texto("A tela volta sozinha quando o sinal voltar.", 17, 0xff6a3d12, true);
        situacao.setBackgroundColor(0xffffe3c2);
        situacao.setPadding(dp(14), dp(12), dp(14), dp(12));
        caixa.addView(situacao, larguraTotal());

        Button botao = new Button(this);
        botao.setText("Tentar agora");
        botao.setAllCaps(false);
        botao.setTextSize(TypedValue.COMPLEX_UNIT_SP, 17);
        botao.setTypeface(Typeface.DEFAULT_BOLD);
        botao.setTextColor(TINTA);
        botao.setBackgroundColor(OURO);
        botao.setMinHeight(dp(56));
        botao.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                tentar();
            }
        });
        LinearLayout.LayoutParams lugar = larguraTotal();
        lugar.topMargin = dp(20);
        caixa.addView(botao, lugar);
        return caixa;
    }

    private TextView texto(String conteudo, int tamanho, int cor, boolean negrito) {
        TextView vista = new TextView(this);
        vista.setText(conteudo);
        vista.setTextSize(TypedValue.COMPLEX_UNIT_SP, tamanho);
        vista.setTextColor(cor);
        vista.setGravity(Gravity.CENTER);
        if (negrito) {
            vista.setTypeface(Typeface.DEFAULT_BOLD);
        }
        return vista;
    }

    private LinearLayout.LayoutParams larguraTotal() {
        LinearLayout.LayoutParams lugar = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        lugar.width = Math.min(getResources().getDisplayMetrics().widthPixels - dp(48), dp(460));
        lugar.gravity = Gravity.CENTER_HORIZONTAL;
        return lugar;
    }

    private void mostrarSemConexao(String endereco) {
        paraAbrir = endereco;
        semConexao.setVisibility(View.VISIBLE);
        web.setVisibility(View.INVISIBLE);
        situacao.setText("A tela volta sozinha quando o sinal voltar.");
        relogio.removeCallbacks(tentarDeNovo);
        relogio.postDelayed(tentarDeNovo, ESPERA_MS);
    }

    /** Pergunta ao sistema se ele responde. Qualquer resposta quer dizer que o sinal voltou. */
    private void tentar() {
        if (tentando || semConexao.getVisibility() != View.VISIBLE) {
            return;
        }
        tentando = true;
        relogio.removeCallbacks(tentarDeNovo);
        situacao.setText("Tentando…");
        new Thread(new Runnable() {
            @Override
            public void run() {
                final boolean voltou = sistemaResponde();
                relogio.post(new Runnable() {
                    @Override
                    public void run() {
                        tentando = false;
                        if (voltou) {
                            semConexao.setVisibility(View.GONE);
                            web.setVisibility(View.VISIBLE);
                            web.loadUrl(paraAbrir);
                        } else {
                            String hora = new SimpleDateFormat("HH:mm", Locale.getDefault()).format(new Date());
                            situacao.setText("Ainda sem sinal (" + hora + "). A tela volta sozinha quando o sinal voltar.");
                            relogio.postDelayed(tentarDeNovo, ESPERA_MS);
                        }
                    }
                });
            }
        }).start();
    }

    private static boolean sistemaResponde() {
        HttpURLConnection conexao = null;
        try {
            conexao = (HttpURLConnection) new URL(ENDERECO + "health").openConnection();
            conexao.setConnectTimeout(8000);
            conexao.setReadTimeout(8000);
            conexao.setUseCaches(false);
            conexao.getResponseCode();
            return true;
        } catch (IOException e) {
            return false;
        } finally {
            if (conexao != null) {
                conexao.disconnect();
            }
        }
    }

    // ---- Ciclo do app ----

    @Override
    public void onBackPressed() {
        if (semConexao.getVisibility() != View.VISIBLE && web.canGoBack()) {
            web.goBack();
            return;
        }
        // Não fecha o app: só vai para o fundo, e volta na mesma tela.
        moveTaskToBack(true);
    }

    @Override
    protected void onResume() {
        super.onResume();
        web.onResume();
    }

    @Override
    protected void onPause() {
        web.onPause();
        CookieManager.getInstance().flush();
        super.onPause();
    }

    @Override
    protected void onSaveInstanceState(Bundle saida) {
        super.onSaveInstanceState(saida);
        web.saveState(saida);
    }

    @Override
    protected void onDestroy() {
        relogio.removeCallbacksAndMessages(null);
        web.destroy();
        super.onDestroy();
    }

    private int dp(int valor) {
        return Math.round(valor * getResources().getDisplayMetrics().density);
    }

    /** Desliga o preenchimento automático do Android (Android 8 em diante), sem depender da versão usada para montar o app. */
    private static void semAutopreenchimento(View vista) {
        try {
            // View.IMPORTANT_FOR_AUTOFILL_NO_EXCLUDE_DESCENDANTS
            View.class.getMethod("setImportantForAutofill", int.class).invoke(vista, 0x8);
        } catch (Exception e) {
            // Android 6 e 7 não têm o preenchimento automático.
        }
    }
}
