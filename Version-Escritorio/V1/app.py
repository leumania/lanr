from flask import jsonify
from werkzeug.utils import secure_filename
from pypdf import PdfReader, PdfWriter
from flask import (
    Flask,
    render_template,
    request,
    redirect,
    url_for,
    session,
    flash,
    abort,
    send_file,
    send_from_directory
)
from werkzeug.security import generate_password_hash, check_password_hash
import sqlite3
import os
import re

import smtplib
import ssl
import hashlib

from email.message import EmailMessage
from itsdangerous import URLSafeTimedSerializer, SignatureExpired, BadSignature

from datetime import datetime, timedelta

import io

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    SimpleDocTemplate,
    Table,
    TableStyle,
    Paragraph,
    Spacer,
    Image,
    KeepTogether
)
from reportlab.pdfgen import canvas
from reportlab.pdfbase.pdfmetrics import stringWidth


BASE = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(BASE, 'lanr.db')

UPLOADS = os.path.join(BASE, 'uploads')
os.makedirs(UPLOADS, exist_ok=True)

app = Flask(__name__)
app.secret_key = 'lanr-local-v1'
app.permanent_session_lifetime = timedelta(hours=2)

MAIL_SENDER = 'sistemas.lanr@gmail.com'
MAIL_APP_PASSWORD = os.environ.get('LANR_GMAIL_APP_PASSWORD')

RESET_TOKEN_MAX_AGE = 1800  # 30 minutos

reset_serializer = URLSafeTimedSerializer(
    app.secret_key,
    salt='lanr-password-reset'
)

app.config['SESSION_REFRESH_EACH_REQUEST'] = True

PROJECT = 'MEJORAMIENTO Y AMPLIACIÓN DEL SERVICIO DE TRANSITABILIDAD VIAL INTERURBANA EN EL PUENTE VEHICULAR SOBRE EL RÍO TABACONAS EN LA LOCALIDAD LA VEGA DEL PUENTE DEL DISTRITO DE SAN JOSE DEL ALTO DE LA PROVINCIA DE JAÉN DEL DEPARTAMENTO DE CAJAMARCA'
PLACE = 'C.P. LA VEGA - DISTRITO SAN JOSE DEL ALTO-PROVINCIA JAEN- REGION CAJAMARCA'

USERS = [
    ('Artidoro Moreno', 'aMoreno', 'Gerencia de Obra'), ('José Luis Becerra',
                                                         'jBecerra', 'Control y Planeamiento'),
    ('Luis Neyra', 'lNeyra', 'Gerencia General'), ('Sara Sanchez',
                                                   'sSanchez', 'Administración'),
    ('Rodrigo Vasquez', 'rVasquez',
     'Logística'), ('Yoana Coronado', 'yCoronado', 'Tesorería'),
    ('Olenka Gonzales', 'oGonzales', 'Sistemas')]


def db():
    c = sqlite3.connect(DB)
    c.row_factory = sqlite3.Row
    return c


def init_db():

    c = db()

    c.executescript('''

    CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY,
        full_name TEXT,
        username TEXT UNIQUE COLLATE NOCASE,
        password_hash TEXT,
        role TEXT,
        email TEXT,
        phone TEXT,
        must_change INTEGER DEFAULT 1,
        active INTEGER DEFAULT 1
    );

    CREATE TABLE IF NOT EXISTS tramites(
        id INTEGER PRIMARY KEY,
        tracking TEXT UNIQUE,
        tipo TEXT,
        subtipo TEXT,
        numero TEXT,
        fecha TEXT,
        proyecto TEXT,
        lugar TEXT,
        beneficiario TEXT,

        dni_ruc TEXT,
        modalidad_pago TEXT,
        responsable TEXT,
        tipo_comprobante TEXT,
        banco TEXT,
        nro_comprobante TEXT,
        cuenta_cci TEXT,
        abono REAL,
        observaciones TEXT,

        estado TEXT,
        formato TEXT,
        creador INTEGER,
        creado TEXT
    );

    CREATE TABLE IF NOT EXISTS items(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        seccion TEXT,
        nro INTEGER,
        descripcion TEXT,
        unidad TEXT,
        cantidad REAL,
        stock REAL,
        comprar REAL,
        justificacion TEXT,
        costo REAL,
        monto REAL
    );

    CREATE TABLE IF NOT EXISTS approvals(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        rol TEXT,
        usuario_id INTEGER,
        aprobado INTEGER DEFAULT 0,
        fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS history(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        usuario_id INTEGER,
        accion TEXT,
        fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS notificaciones(
        id INTEGER PRIMARY KEY,
        usuario_id INTEGER,
        tramite_id INTEGER,
        titulo TEXT,
        mensaje TEXT,
        tipo TEXT DEFAULT 'informativa',
        leida INTEGER DEFAULT 0,
        fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS attachments(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        nombre_original TEXT,
        nombre_archivo TEXT,
        orden INTEGER,
        fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS gestion_logistica(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER UNIQUE,
        recibido_fecha TEXT,

        cotizacion_estado TEXT DEFAULT 'Pendiente',

        proveedor TEXT,
        ruc TEXT,
        fecha_compra TEXT,
        tipo_comprobante TEXT,
        nro_comprobante TEXT,
        monto REAL DEFAULT 0,

        comprobante_pendiente INTEGER DEFAULT 0,

        forma_pago TEXT,
        estado_pago TEXT,

        guia_numero TEXT,
        guia_fecha TEXT,
        guia_pendiente INTEGER DEFAULT 0,

        enviado_fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS archivos_logistica(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        tipo TEXT,
        nombre_original TEXT,
        nombre_archivo TEXT,
        fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS solicitudes_tesoreria(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        solicitado_por INTEGER,
        motivo TEXT,
        monto REAL,
        estado TEXT DEFAULT 'Pendiente',
        fecha_solicitud TEXT,
        fecha_atencion TEXT
    );

    CREATE TABLE IF NOT EXISTS pagos_tesoreria(
        id INTEGER PRIMARY KEY,
        solicitud_id INTEGER,
        nombre_original TEXT,
        nombre_archivo TEXT,
        fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS regularizaciones(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER,
        responsable_id INTEGER,
        tipo TEXT,
        descripcion TEXT,
        estado TEXT DEFAULT 'Pendiente',
        fecha_creacion TEXT,
        fecha_regularizacion TEXT
    );

    CREATE TABLE IF NOT EXISTS gestion_sp(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER UNIQUE,

        asignado_pago TEXT,
        asignado_por INTEGER,
        fecha_asignacion TEXT,

        pagado_por INTEGER,
        medio_pago TEXT,
        banco_pago TEXT,
        nro_operacion TEXT,
        monto_pagado REAL,
        fecha_pago TEXT,

        nombre_original_pago TEXT,
        nombre_archivo_pago TEXT,

        conformidad_gg INTEGER DEFAULT 0,
        fecha_conformidad TEXT
    );

    ''')

    # =========================================================
    # MIGRACIONES DE BASE DE DATOS
    # =========================================================

    columnas_gestion = [
        row['name']
        for row in c.execute(
            "PRAGMA table_info(gestion_logistica)"
        ).fetchall()
    ]

    if 'requiere_reembolso' not in columnas_gestion:
        c.execute(
            '''
            ALTER TABLE gestion_logistica
            ADD COLUMN requiere_reembolso INTEGER DEFAULT 0
            '''
        )

    columnas_tesoreria = [
        row['name']
        for row in c.execute(
            "PRAGMA table_info(solicitudes_tesoreria)"
        ).fetchall()
    ]

    if 'origen' not in columnas_tesoreria:
        c.execute(
            '''
            ALTER TABLE solicitudes_tesoreria
            ADD COLUMN origen TEXT
            '''
        )

    # =========================================================
    # CAMPOS PARA REGISTRAR PAGOS DE TESORERÍA
    # =========================================================

    columnas_pagos = [
        row['name']
        for row in c.execute(
            "PRAGMA table_info(pagos_tesoreria)"
        ).fetchall()
    ]

    if 'medio_pago' not in columnas_pagos:
        c.execute(
            '''
            ALTER TABLE pagos_tesoreria
            ADD COLUMN medio_pago TEXT
            '''
        )

    if 'banco' not in columnas_pagos:
        c.execute(
            '''
            ALTER TABLE pagos_tesoreria
            ADD COLUMN banco TEXT
            '''
        )

    if 'monto' not in columnas_pagos:
        c.execute(
            '''
            ALTER TABLE pagos_tesoreria
            ADD COLUMN monto REAL
            '''
        )

    if 'nro_operacion' not in columnas_pagos:
        c.execute(
            '''
            ALTER TABLE pagos_tesoreria
            ADD COLUMN nro_operacion TEXT
            '''
        )

    for n, u, r in USERS:

        if not c.execute(
            'select 1 from users where username=? collate nocase',
            (u,)
        ).fetchone():

            c.execute(
                'insert into users(full_name,username,password_hash,role) values(?,?,?,?)',
                (n, u, generate_password_hash('obra2026'), r)
            )

    c.commit()
    c.close()


def user():
    if 'uid' not in session:
        return None
    c = db()
    u = c.execute('select * from users where id=?',
                  (session['uid'],)).fetchone()
    c.close()
    return u


@app.context_processor
def load_notifications():

    if 'uid' not in session:
        return {
            'notificaciones_usuario': [],
            'notificaciones_no_leidas': 0
        }

    c = db()

    notificaciones_usuario = c.execute(
        '''
        SELECT
            n.*,
            t.tracking
        FROM notificaciones n

        LEFT JOIN tramites t
            ON t.id = n.tramite_id

        WHERE n.usuario_id=?

        ORDER BY
            n.leida ASC,
            n.id DESC

        LIMIT 10
        ''',
        (session['uid'],)
    ).fetchall()

    notificaciones_no_leidas = c.execute(
        '''
        SELECT COUNT(*) AS c
        FROM notificaciones
        WHERE usuario_id=?
        AND leida=0
        ''',
        (session['uid'],)
    ).fetchone()['c']

    c.close()

    return {
        'notificaciones_usuario': notificaciones_usuario,
        'notificaciones_no_leidas': notificaciones_no_leidas
    }


def login_required(fn):
    from functools import wraps

    @wraps(fn)
    def w(*a, **k):
        if 'uid' not in session:
            return redirect(url_for('login'))
        return fn(*a, **k)
    return w


def next_code(tipo, year):
    p = 'REQ' if tipo == 'REQ' else 'SP'
    c = db()
    row = c.execute('select tracking FROM tramites where tracking like ? order by id desc limit 1',
                    (f'{p}-LAVEGA-{year}-%',)).fetchone()
    c.close()
    n = int(row['tracking'].split('-')[-1])+1 if row else 1
    return f'{p}-LAVEGA-{year}-{n:03d}'


def log(tid, uid, accion):
    c = db()
    c.execute('insert into history(tramite_id,usuario_id,accion,fecha) values(?,?,?,?)',
              (tid, uid, accion, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
    c.commit()
    c.close()


def notify(usuario_id, tramite_id, titulo, mensaje, tipo='informativa'):
    c = db()

    c.execute(
        '''
        INSERT INTO notificaciones(
            usuario_id,
            tramite_id,
            titulo,
            mensaje,
            tipo,
            leida,
            fecha
        )
        VALUES(?,?,?,?,?,0,?)
        ''',
        (
            usuario_id,
            tramite_id,
            titulo,
            mensaje,
            tipo,
            datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        )
    )

    c.commit()
    c.close()


def password_fingerprint(password_hash):
    return hashlib.sha256(
        password_hash.encode('utf-8')
    ).hexdigest()[:16]


def generate_reset_token(usuario):
    return reset_serializer.dumps({
        'uid': usuario['id'],
        'fingerprint': password_fingerprint(
            usuario['password_hash']
        )
    })


def verify_reset_token(token):
    try:
        data = reset_serializer.loads(
            token,
            max_age=RESET_TOKEN_MAX_AGE
        )

    except SignatureExpired:
        return None

    except BadSignature:
        return None

    c = db()

    usuario = c.execute(
        'SELECT * FROM users WHERE id=?',
        (data.get('uid'),)
    ).fetchone()

    c.close()

    if not usuario:
        return None

    fingerprint_actual = password_fingerprint(
        usuario['password_hash']
    )

    if data.get('fingerprint') != fingerprint_actual:
        return None

    return usuario


def send_password_reset_email(usuario, reset_url):
    if not MAIL_APP_PASSWORD:
        raise RuntimeError(
            'No se configuró LANR_GMAIL_APP_PASSWORD.'
        )

    mensaje = EmailMessage()

    mensaje['Subject'] = 'Recuperación de contraseña - LANR'
    mensaje['From'] = MAIL_SENDER
    mensaje['To'] = usuario['email']

    mensaje.set_content(
        f"""Hola {usuario['full_name']},

Se solicitó restablecer la contraseña de tu cuenta en el sistema LANR.

Abre el siguiente enlace para crear una nueva contraseña:

{reset_url}

Este enlace será válido durante 30 minutos.

Si no solicitaste este cambio, puedes ignorar este mensaje.

LANR INVERSIONES E.I.R.L.
Área de Sistemas
"""
    )

    contexto = ssl.create_default_context()

    with smtplib.SMTP(
        'smtp.gmail.com',
        587,
        timeout=20
    ) as servidor:

        servidor.starttls(context=contexto)

        servidor.login(
            MAIL_SENDER,
            MAIL_APP_PASSWORD
        )

        servidor.send_message(mensaje)


REQUEST_CREATORS = ('amoreno', 'jbecerra', 'ogonzales')


def can_create_request(usuario):
    return usuario['username'].lower() in REQUEST_CREATORS


def parse_decimal(valor):
    try:
        return float(
            str(valor or '0')
            .replace(',', '')
            .strip()
        )
    except:
        return 0.0


def can_office_view_request(tid):
    c = db()

    total = c.execute(
        '''
        SELECT COUNT(*) AS c
        FROM approvals
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()['c']

    aprobados = c.execute(
        '''
        SELECT COUNT(*) AS c
        FROM approvals
        WHERE tramite_id=?
        AND aprobado=1
        ''',
        (tid,)
    ).fetchone()['c']

    c.close()

    return total >= 2 and aprobados == total


def save_logistics_files(c, tid, archivos, tipo):
    permitidos = ('.pdf', '.png', '.jpg', '.jpeg', '.webp', '.xml')

    for idx, archivo in enumerate(archivos, start=1):

        if not archivo or not archivo.filename:
            continue

        if not archivo.filename.lower().endswith(permitidos):
            continue

        original = archivo.filename

        seguro = secure_filename(
            f'LOG_{tid}_{tipo}_{idx}_{archivo.filename}'
        )

        archivo.save(
            os.path.join(UPLOADS, seguro)
        )

        c.execute(
            '''
            INSERT INTO archivos_logistica(
                tramite_id,
                tipo,
                nombre_original,
                nombre_archivo,
                fecha
            )
            VALUES(?,?,?,?,?)
            ''',
            (
                tid,
                tipo,
                original,
                seguro,
                datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            )
        )


def create_regularization(
    c,
    tramite_id,
    responsable_id,
    tipo,
    descripcion
):

    existe = c.execute(
        '''
        SELECT id
        FROM regularizaciones
        WHERE tramite_id=?
          AND responsable_id=?
          AND tipo=?
          AND estado='Pendiente'
        ''',
        (
            tramite_id,
            responsable_id,
            tipo
        )
    ).fetchone()

    if existe:
        return

    c.execute(
        '''
        INSERT INTO regularizaciones(
            tramite_id,
            responsable_id,
            tipo,
            descripcion,
            estado,
            fecha_creacion
        )
        VALUES(?,?,?,?,?,?)
        ''',
        (
            tramite_id,
            responsable_id,
            tipo,
            descripcion,
            'Pendiente',
            datetime.now().strftime(
                '%Y-%m-%d %H:%M:%S'
            )
        )
    )


def generate_requirement_pdf(tid):
    c = db()

    t = c.execute(
        '''
        SELECT t.*, u.full_name creador_nombre
        FROM tramites t
        JOIN users u ON u.id = t.creador
        WHERE t.id = ?
        ''',
        (tid,)
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if t['tipo'] != 'REQ':
        c.close()
        abort(400)

    items = c.execute(
        '''
        SELECT *
        FROM items
        WHERE tramite_id = ?
        ORDER BY id
        ''',
        (tid,)
    ).fetchall()

    approvals = c.execute(
        '''
        SELECT
            a.*,
            u.full_name nombre
        FROM approvals a
        JOIN users u ON u.id = a.usuario_id
        WHERE a.tramite_id = ?
        ORDER BY a.id
        ''',
        (tid,)
    ).fetchall()

    c.close()

    # =====================================================
    # FECHA
    # =====================================================

    try:
        fecha_obj = datetime.strptime(
            t['fecha'],
            '%Y-%m-%d'
        )

        fecha_mostrar = fecha_obj.strftime(
            '%d/%m/%Y'
        )

        fecha_archivo = fecha_obj.strftime(
            '%d-%m-%Y'
        )

    except:
        fecha_mostrar = str(t['fecha'])
        fecha_archivo = str(t['fecha']).replace('/', '-')

    numero_corto = t['numero'].split('-')[0]

    # =====================================================
    # PERIODO
    # =====================================================

    secciones = []

    for item in items:
        if item['seccion'] and item['seccion'] not in secciones:
            secciones.append(item['seccion'])

    if len(secciones) == 1:

        if secciones[0] == 'Ejecución de obra':
            periodo = 'EJECUCIÓN'

        elif secciones[0] == 'Ing. de seguridad':
            periodo = 'ING. SEGURIDAD'

        else:
            periodo = secciones[0].upper()

    elif len(secciones) > 1:

        periodo = 'EJECUCIÓN'

    else:

        periodo = ''

    # =====================================================
    # PDF
    # =====================================================

    buffer = io.BytesIO()

    pdf = canvas.Canvas(
        buffer,
        pagesize=A4
    )

    ancho_pagina, alto_pagina = A4

    # =====================================================
    # MEDIDAS GENERALES
    # =====================================================

    izquierda = 8 * mm
    derecha = ancho_pagina - 8 * mm

    ancho_total = derecha - izquierda

    y = alto_pagina - 10 * mm

    negro = colors.black
    azul = colors.HexColor('#D9EAF7')
    durazno = colors.HexColor('#FBE6DB')
    rojo = colors.HexColor('#E31B23')
    verde = colors.HexColor('#28734D')
    naranja = colors.HexColor('#B06B00')
    gris = colors.HexColor('#666666')

    # =====================================================
    # FUNCIONES AUXILIARES
    # =====================================================

    def rect(x, y_abajo, w, h, fill=None, grosor=0.7):
        pdf.setLineWidth(grosor)

        if fill:
            pdf.setFillColor(fill)
            pdf.rect(
                x,
                y_abajo,
                w,
                h,
                stroke=1,
                fill=1
            )
            pdf.setFillColor(negro)

        else:
            pdf.rect(
                x,
                y_abajo,
                w,
                h,
                stroke=1,
                fill=0
            )

    def draw_centered_text(
        texto,
        x,
        y_centro,
        w,
        font='Helvetica',
        size=7,
        color=negro
    ):
        pdf.setFont(font, size)
        pdf.setFillColor(color)

        pdf.drawCentredString(
            x + w / 2,
            y_centro - size / 3,
            str(texto)
        )

        pdf.setFillColor(negro)

    def draw_left_text(
        texto,
        x,
        y_centro,
        size=7,
        font='Helvetica',
        color=negro
    ):
        pdf.setFont(font, size)
        pdf.setFillColor(color)

        pdf.drawString(
            x,
            y_centro - size / 3,
            str(texto)
        )

        pdf.setFillColor(negro)

    def wrap_text(
        texto,
        ancho,
        font='Helvetica',
        size=6
    ):
        texto = str(texto or '')

        palabras = texto.split()

        lineas = []
        linea = ''

        for palabra in palabras:

            prueba = (
                linea + ' ' + palabra
            ).strip()

            if stringWidth(
                prueba,
                font,
                size
            ) <= ancho:

                linea = prueba

            else:

                if linea:
                    lineas.append(linea)

                linea = palabra

        if linea:
            lineas.append(linea)

        return lineas

    def draw_multiline_text(
        texto,
        x,
        y_superior,
        ancho,
        alto,
        font='Helvetica',
        size=6,
        color=negro,
        draw_centered=False
    ):
        lineas = wrap_text(
            texto,
            ancho - 4,
            font,
            size
        )

        max_lineas = max(
            1,
            int(alto / (size + 1))
        )

        lineas = lineas[:max_lineas]

        altura_texto = len(lineas) * (size + 1)

        yy = (
            y_superior
            - (alto - altura_texto) / 2
            - size
        )

        pdf.setFont(
            font,
            size
        )

        pdf.setFillColor(
            color
        )

        for linea in lineas:

            if draw_centered:

                pdf.drawCentredString(
                    x + ancho / 2,
                    yy,
                    linea
                )

            else:

                pdf.drawString(
                    x + 2,
                    yy,
                    linea
                )

            yy -= size + 1

        pdf.setFillColor(
            negro
        )

    # =====================================================
    # BORDE EXTERIOR
    # =====================================================

    pdf.setLineWidth(1.3)

    pdf.rect(
        izquierda - 1.5 * mm,
        44 * mm,
        ancho_total + 3 * mm,
        alto_pagina - 54 * mm,
        stroke=1,
        fill=0
    )

    # =====================================================
    # CABECERA
    # =====================================================

    alto_header = 25 * mm

    x_logo = izquierda
    w_logo = 34 * mm

    x_titulo = x_logo + w_logo
    w_titulo = 106 * mm

    x_datos = x_titulo + w_titulo
    w_datos = ancho_total - w_logo - w_titulo

    y_header_abajo = y - alto_header

    rect(
        x_logo,
        y_header_abajo,
        w_logo,
        alto_header
    )

    rect(
        x_titulo,
        y_header_abajo,
        w_titulo,
        alto_header
    )

    rect(
        x_datos,
        y_header_abajo,
        w_datos,
        alto_header
    )

    # LOGO

    logo_path = os.path.join(
        BASE,
        'static',
        'logo-lanr.jpeg'
    )

    if os.path.exists(logo_path):

        pdf.drawImage(
            logo_path,
            x_logo + 2 * mm,
            y_header_abajo + 2 * mm,
            width=w_logo - 4 * mm,
            height=alto_header - 4 * mm,
            preserveAspectRatio=True,
            anchor='c'
        )

    # TÍTULO

    draw_centered_text(
        'REQUERIMIENTO DE MATERIALES',
        x_titulo,
        y_header_abajo + alto_header / 2,
        w_titulo,
        'Helvetica-Bold',
        10
    )

    # DATOS DERECHA

    fila_dato = alto_header / 3

    w_etiqueta = 20 * mm
    w_valor = w_datos - w_etiqueta

    etiquetas = [
        ('N°', t['numero']),
        ('FECHA SOLICITUD', fecha_mostrar),
        ('PERIODO USO', periodo)
    ]

    for idx, (label, valor) in enumerate(etiquetas):

        yy = (
            y_header_abajo
            + alto_header
            - ((idx + 1) * fila_dato)
        )

        rect(
            x_datos,
            yy,
            w_etiqueta,
            fila_dato
        )

        rect(
            x_datos + w_etiqueta,
            yy,
            w_valor,
            fila_dato
        )

        draw_left_text(
            label,
            x_datos + 1.5 * mm,
            yy + fila_dato / 2,
            5.2,
            'Helvetica-Bold',
            rojo if idx == 0 else negro
        )

        draw_centered_text(
            valor,
            x_datos + w_etiqueta,
            yy + fila_dato / 2,
            w_valor,
            'Helvetica-Bold' if idx == 0 else 'Helvetica',
            5.8
        )

    y = y_header_abajo - 3 * mm

    # =====================================================
    # PROYECTO
    # =====================================================

    alto_proyecto = 13 * mm
    ancho_etiqueta = 34 * mm

    rect(
        izquierda,
        y - alto_proyecto,
        ancho_etiqueta,
        alto_proyecto,
        durazno
    )

    rect(
        izquierda + ancho_etiqueta,
        y - alto_proyecto,
        ancho_total - ancho_etiqueta,
        alto_proyecto
    )

    draw_left_text(
        'NOMBRE DEL PROYECTO',
        izquierda + 1.5 * mm,
        y - alto_proyecto / 2,
        5.5,
        'Helvetica-Bold'
    )

    draw_multiline_text(
        t['proyecto'],
        izquierda + ancho_etiqueta,
        y,
        ancho_total - ancho_etiqueta,
        alto_proyecto,
        'Helvetica-Bold',
        5.6
    )

    y -= alto_proyecto

    # =====================================================
    # LUGAR
    # =====================================================

    alto_lugar = 7 * mm

    rect(
        izquierda,
        y - alto_lugar,
        ancho_etiqueta,
        alto_lugar,
        durazno
    )

    rect(
        izquierda + ancho_etiqueta,
        y - alto_lugar,
        ancho_total - ancho_etiqueta,
        alto_lugar
    )

    draw_left_text(
        'LUGAR',
        izquierda + 1.5 * mm,
        y - alto_lugar / 2,
        5.5,
        'Helvetica-Bold'
    )

    draw_left_text(
        t['lugar'],
        izquierda + ancho_etiqueta + 1.5 * mm,
        y - alto_lugar / 2,
        5.5
    )

    y -= alto_lugar + 3 * mm

    # =====================================================
    # RELACIÓN
    # =====================================================

    draw_left_text(
        'RELACIÓN DE PRODUCTOS',
        izquierda,
        y,
        6.5,
        'Helvetica-Bold'
    )

    y -= 4 * mm

    # =====================================================
    # COLUMNAS
    # =====================================================

    columnas = [
        8 * mm,
        67 * mm,
        13 * mm,
        11 * mm,
        14 * mm,
        15 * mm,
        54 * mm,
        12 * mm
    ]

    titulos = [
        'Item',
        'DESCRIPCIÓN',
        'CANTIDAD',
        'UND',
        'STOCK\nALMACEN',
        'CANTIDAD A\nCOMPRAR',
        'JUSTIFICACIÓN',
        'META'
    ]

    x_cols = [
        izquierda
    ]

    for w in columnas[:-1]:
        x_cols.append(
            x_cols[-1] + w
        )

    alto_cabecera_tabla = 10 * mm

    # =====================================================
    # CABECERA TABLA
    # =====================================================

    for idx, titulo in enumerate(titulos):

        rect(
            x_cols[idx],
            y - alto_cabecera_tabla,
            columnas[idx],
            alto_cabecera_tabla
        )

        partes = titulo.split('\n')

        if len(partes) == 1:

            draw_centered_text(
                partes[0],
                x_cols[idx],
                y - alto_cabecera_tabla / 2,
                columnas[idx],
                'Helvetica-Bold',
                5.4
            )

        else:

            draw_multiline_text(
                titulo.replace('\n', ' '),
                x_cols[idx],
                y,
                columnas[idx],
                alto_cabecera_tabla,
                'Helvetica-Bold',
                4.8,
                negro,
                True
            )

    y -= alto_cabecera_tabla

    # =====================================================
    # ALTURA DE FILAS
    # =====================================================

    total_items = len(items)

    if total_items <= 8:
        alto_item = 7 * mm

    elif total_items <= 12:
        alto_item = 6 * mm

    elif total_items <= 16:
        alto_item = 5.5 * mm

    else:
        alto_item = 5 * mm

    alto_seccion = 5.5 * mm

    # =====================================================
    # PRODUCTOS
    # =====================================================

    for seccion in secciones:

        # SECCIÓN AZUL

        rect(
            izquierda,
            y - alto_seccion,
            ancho_total,
            alto_seccion,
            azul
        )

        draw_centered_text(
            seccion.upper(),
            izquierda,
            y - alto_seccion / 2,
            ancho_total,
            'Helvetica-Bold',
            6
        )

        y -= alto_seccion

        items_seccion = [
            i for i in items
            if i['seccion'] == seccion
        ]

        for item in items_seccion:

            valores = []

            nro = item['nro']

            try:
                nro = f'{int(nro):02d}'
            except:
                nro = str(nro or '')

            cantidad = item['cantidad']

            if cantidad is not None:
                try:
                    cantidad = (
                        int(cantidad)
                        if float(cantidad).is_integer()
                        else cantidad
                    )
                except:
                    pass

            stock = item['stock']

            if stock in (
                None,
                0,
                0.0,
                ''
            ):
                stock = ''

            else:
                try:
                    stock = (
                        int(stock)
                        if float(stock).is_integer()
                        else stock
                    )
                except:
                    pass

            comprar = item['comprar']

            if comprar is not None:
                try:
                    comprar = (
                        int(comprar)
                        if float(comprar).is_integer()
                        else comprar
                    )
                except:
                    pass

            valores = [
                nro,
                item['descripcion'] or '',
                cantidad or '',
                item['unidad'] or '',
                stock,
                comprar or '',
                item['justificacion'] or '',
                ''
            ]

            for idx, valor in enumerate(valores):

                rect(
                    x_cols[idx],
                    y - alto_item,
                    columnas[idx],
                    alto_item
                )

                if idx in (1, 6):

                    draw_multiline_text(
                        valor,
                        x_cols[idx],
                        y,
                        columnas[idx],
                        alto_item,
                        'Helvetica',
                        5.4,
                        rojo if idx == 6 else negro,
                        idx == 6
                    )

                else:

                    draw_centered_text(
                        valor,
                        x_cols[idx],
                        y - alto_item / 2,
                        columnas[idx],
                        'Helvetica',
                        5.8
                    )

            y -= alto_item

    # =====================================================
    # FILAS VACÍAS
    # =====================================================

    filas_actuales = len(items)

    filas_vacias = max(
        2,
        10 - filas_actuales
    )

    for _ in range(filas_vacias):

        for idx in range(len(columnas)):

            rect(
                x_cols[idx],
                y - alto_item,
                columnas[idx],
                alto_item
            )

        y -= alto_item

    # =====================================================
    # APROBACIONES
    # =====================================================

    ancho_aprobacion = ancho_total / 2

    alto_titulo_aprobacion = 6 * mm
    alto_firma = 28 * mm

    roles_orden = [
        'Planeamiento',
        'Gerencia de Obra'
    ]

    approval_dict = {
        a['rol']: a
        for a in approvals
    }

    for idx, rol in enumerate(roles_orden):

        x = (
            izquierda
            + idx * ancho_aprobacion
        )

        # TÍTULO

        rect(
            x,
            y - alto_titulo_aprobacion,
            ancho_aprobacion,
            alto_titulo_aprobacion
        )

        draw_centered_text(
            rol,
            x,
            y - alto_titulo_aprobacion / 2,
            ancho_aprobacion,
            'Helvetica-Bold',
            5.7
        )

        # FIRMA

        rect(
            x,
            y - alto_titulo_aprobacion - alto_firma,
            ancho_aprobacion,
            alto_firma
        )

        a = approval_dict.get(rol)

        if a:

            if a['aprobado']:

                draw_centered_text(
                    'APROBADO',
                    x,
                    y - alto_titulo_aprobacion - 8 * mm,
                    ancho_aprobacion,
                    'Helvetica-Bold',
                    6,
                    verde
                )

                draw_centered_text(
                    a['nombre'],
                    x,
                    y - alto_titulo_aprobacion - 13 * mm,
                    ancho_aprobacion,
                    'Helvetica-Bold',
                    5.5
                )

                fecha_aprobacion = ''

                if a['fecha']:

                    try:

                        fecha_aprobacion = datetime.strptime(
                            a['fecha'],
                            '%Y-%m-%d %H:%M:%S'
                        ).strftime(
                            '%d/%m/%Y - %H:%M'
                        )

                    except:

                        fecha_aprobacion = a['fecha']

                draw_centered_text(
                    fecha_aprobacion,
                    x,
                    y - alto_titulo_aprobacion - 18 * mm,
                    ancho_aprobacion,
                    'Helvetica',
                    5
                )

            else:

                draw_centered_text(
                    'PENDIENTE DE V°B°',
                    x,
                    y - alto_titulo_aprobacion - 11 * mm,
                    ancho_aprobacion,
                    'Helvetica-Bold',
                    5.5,
                    naranja
                )

                draw_centered_text(
                    a['nombre'],
                    x,
                    y - alto_titulo_aprobacion - 16 * mm,
                    ancho_aprobacion,
                    'Helvetica',
                    5
                )

    y -= (
        alto_titulo_aprobacion
        + alto_firma
    )

    # =====================================================
    # CÓDIGO DE FORMATO
    # =====================================================

    pdf.setFont(
        'Helvetica-Bold',
        5.5
    )

    pdf.drawRightString(
        derecha - 8 * mm,
        y - 4 * mm,
        t['formato'] or ''
    )

    # =====================================================
    # FINAL
    # =====================================================

    pdf.save()

    buffer.seek(0)

    nombre_pdf = (
        f'REQ LANR '
        f'{numero_corto} '
        f'{fecha_archivo}.pdf'
    )

    return buffer, nombre_pdf


def generate_payment_request_pdf(tid):

    c = db()

    t = c.execute(
        '''
        SELECT t.*, u.full_name creador_nombre
        FROM tramites t
        JOIN users u ON u.id = t.creador
        WHERE t.id = ?
        ''',
        (tid,)
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if t['tipo'] != 'SP':
        c.close()
        abort(400)

    items = c.execute(
        '''
        SELECT *
        FROM items
        WHERE tramite_id = ?
        ORDER BY nro
        ''',
        (tid,)
    ).fetchall()

    approvals = c.execute(
        '''
        SELECT a.*, u.full_name nombre
        FROM approvals a
        JOIN users u ON u.id = a.usuario_id
        WHERE a.tramite_id = ?
        ORDER BY a.id
        ''',
        (tid,)
    ).fetchall()

    adjuntos = c.execute(
        '''
        SELECT *
        FROM attachments
        WHERE tramite_id = ?
        ORDER BY orden, id
        ''',
        (tid,)
    ).fetchall()

    c.close()

    # =====================================================
    # FECHA
    # =====================================================

    try:
        fecha_obj = datetime.strptime(t['fecha'], '%Y-%m-%d')
        fecha_mostrar = fecha_obj.strftime('%d/%m/%Y')
        fecha_archivo = fecha_obj.strftime('%d-%m-%Y')

    except:
        fecha_mostrar = str(t['fecha'])
        fecha_archivo = str(t['fecha']).replace('/', '-')

    # =====================================================
    # TOTAL / ABONO AUTOMÁTICO
    # =====================================================

    total = sum(
        float(i['monto'] or 0)
        for i in items
    )

    # =====================================================
    # PDF BASE
    # =====================================================

    formato_buffer = io.BytesIO()

    pdf = canvas.Canvas(
        formato_buffer,
        pagesize=A4
    )

    W, H = A4

    # =====================================================
    # COLORES
    # =====================================================

    negro = colors.black

    azul = colors.HexColor('#0070C0')

    gris = colors.HexColor('#D9D9D9')

    amarillo = colors.HexColor('#FFF200')

    rojo = colors.HexColor('#FF0000')

    verde = colors.HexColor('#28734D')

    naranja = colors.HexColor('#B36B00')

    # =====================================================
    # TAMAÑO GENERAL DEL FORMATO
    # =====================================================

    # Más parecido al formato real:
    # centrado y sin ocupar todo el ancho de la hoja.

    ancho_formato = 160 * mm

    izquierda = (W - ancho_formato) / 2

    derecha = izquierda + ancho_formato

    y = H - 24 * mm

    # =====================================================
    # FUNCIONES
    # =====================================================

    def draw_box(x, yb, w, h, fill=None, lw=0.75):

        pdf.setLineWidth(lw)

        if fill:

            pdf.setFillColor(fill)

            pdf.rect(
                x,
                yb,
                w,
                h,
                stroke=1,
                fill=1
            )

            pdf.setFillColor(negro)

        else:

            pdf.rect(
                x,
                yb,
                w,
                h,
                stroke=1,
                fill=0
            )

    def draw_centered(
        texto,
        x,
        yc,
        w,
        size=6,
        bold=False,
        color=negro
    ):

        pdf.setFillColor(color)

        pdf.setFont(
            'Helvetica-Bold' if bold else 'Helvetica',
            size
        )

        pdf.drawCentredString(
            x + w / 2,
            yc - size / 3,
            str(texto or '')
        )

        pdf.setFillColor(negro)

    def draw_left(
        texto,
        x,
        yc,
        size=6,
        bold=False,
        color=negro
    ):

        pdf.setFillColor(color)

        pdf.setFont(
            'Helvetica-Bold' if bold else 'Helvetica',
            size
        )

        pdf.drawString(
            x,
            yc - size / 3,
            str(texto or '')
        )

        pdf.setFillColor(negro)

    def wrap_text(
        texto,
        ancho_max,
        size=5.5,
        font='Helvetica'
    ):

        texto = str(texto or '')

        palabras = texto.split()

        lineas = []

        actual = ''

        for palabra in palabras:

            prueba = (
                actual + ' ' + palabra
            ).strip()

            if stringWidth(
                prueba,
                font,
                size
            ) <= ancho_max:

                actual = prueba

            else:

                if actual:
                    lineas.append(actual)

                actual = palabra

        if actual:
            lineas.append(actual)

        return lineas

    def draw_multiline(
        texto,
        x,
        ysup,
        w,
        h,
        size=5.5,
        bold=False,
        center=False,
        color=negro
    ):

        font = (
            'Helvetica-Bold'
            if bold
            else 'Helvetica'
        )

        ls = wrap_text(
            texto,
            w - 4,
            size,
            font
        )

        alto_linea = size + 1

        max_lineas = max(
            1,
            int(h / alto_linea)
        )

        ls = ls[:max_lineas]

        alto_texto = (
            len(ls) * alto_linea
        )

        yy = (
            ysup
            - ((h - alto_texto) / 2)
            - size
        )

        pdf.setFont(
            font,
            size
        )

        pdf.setFillColor(
            color
        )

        for linea in ls:

            if center:

                pdf.drawCentredString(
                    x + w / 2,
                    yy,
                    linea
                )

            else:

                pdf.drawString(
                    x + 2,
                    yy,
                    linea
                )

            yy -= alto_linea

        pdf.setFillColor(
            negro
        )

    def format_money(valor):

        return (
            f'S/ {float(valor or 0):,.2f}'
        )

    def format_number(valor):

        try:

            n = float(valor)

            if n.is_integer():
                return str(int(n))

            return str(n)

        except:

            return str(valor or '')

    # =====================================================
    # ENCABEZADO
    # =====================================================

    logo_path = os.path.join(
        BASE,
        'static',
        'logo-lanr.jpeg'
    )

    # LOGO

    logo_w = 33 * mm
    logo_h = 12 * mm

    if os.path.exists(logo_path):

        pdf.drawImage(
            logo_path,
            izquierda,
            y - logo_h,
            width=logo_w,
            height=logo_h,
            preserveAspectRatio=True,
            anchor='c'
        )

    # RUC

    draw_left(
        'RUC N° 20609096838',
        izquierda,
        y - logo_h - 3 * mm,
        6,
        True
    )

    # =====================================================
    # CUADRO SOLICITUD DE PAGO
    # =====================================================

    cuadro_w = 57 * mm

    cuadro_x = (
        derecha - cuadro_w
    )

    titulo_h = 6 * mm

    numero_h = 6 * mm

    draw_box(
        cuadro_x,
        y - titulo_h,
        cuadro_w,
        titulo_h,
        gris
    )

    draw_centered(
        'SOLICITUD DE PAGO',
        cuadro_x,
        y - titulo_h / 2,
        cuadro_w,
        7,
        True,
        azul
    )

    draw_box(
        cuadro_x,
        y - titulo_h - numero_h,
        21 * mm,
        numero_h
    )

    draw_box(
        cuadro_x + 21 * mm,
        y - titulo_h - numero_h,
        cuadro_w - 21 * mm,
        numero_h
    )

    draw_left(
        'N°',
        cuadro_x + 3 * mm,
        y - titulo_h - numero_h / 2,
        6,
        True
    )

    draw_centered(
        t['numero'],
        cuadro_x + 21 * mm,
        y - titulo_h - numero_h / 2,
        cuadro_w - 21 * mm,
        6,
        False
    )

    # =====================================================
    # PROYECTO
    # =====================================================

    y -= 19 * mm

    draw_multiline(
        '"' + str(t['proyecto'] or '') + '"',
        izquierda,
        y,
        ancho_formato,
        10 * mm,
        4.8,
        False,
        True
    )

    y -= 13 * mm

    # =====================================================
    # DATOS SOLICITUD
    # =====================================================

    etiqueta_izq = 21 * mm

    valor_izq = 78 * mm

    etiqueta_der = 26 * mm

    valor_der = (
        ancho_formato
        - etiqueta_izq
        - valor_izq
        - etiqueta_der
    )

    fila_h = 6 * mm

    # -----------------------------------------------------
    # PLANILLA
    # -----------------------------------------------------

    if t['subtipo'] == 'Planilla':

        filas = [

            (
                'BENEFICIARIO',
                t['beneficiario'],
                'FECHA',
                fecha_mostrar
            ),

            (
                'DNI/RUC',
                t['dni_ruc'],
                '',
                ''
            ),

            (
                'MODALIDAD DE PAGO',
                t['modalidad_pago'],
                'ABONO',
                total
            ),

            (
                'RESPONSABLE',
                t['responsable'],
                '',
                ''
            )

        ]

    # -----------------------------------------------------
    # PERSONA / EMPRESA
    # -----------------------------------------------------

    else:

        filas = [

            (
                'BENEFICIARIO',
                t['beneficiario'],
                'FECHA',
                fecha_mostrar
            ),

            (
                'DNI/RUC',
                t['dni_ruc'],
                '',
                ''
            ),

            (
                'MODALIDAD DE PAGO',
                t['modalidad_pago'],
                'TIPO COMPROBANTE',
                t['tipo_comprobante']
            ),

            (
                'BANCO',
                t['banco'],
                'N° COMPROBANTE',
                t['nro_comprobante']
            ),

            (
                'CUENTA/CCI',
                t['cuenta_cci'],
                'ABONO',
                total
            )

        ]

    for (
        izq_lab,
        izq_val,
        der_lab,
        der_valor
    ) in filas:

        # Etiqueta izquierda

        draw_box(
            izquierda,
            y - fila_h,
            etiqueta_izq,
            fila_h,
            gris
        )

        # Valor izquierda

        draw_box(
            izquierda + etiqueta_izq,
            y - fila_h,
            valor_izq,
            fila_h
        )

        # Etiqueta derecha

        draw_box(
            izquierda
            + etiqueta_izq
            + valor_izq,
            y - fila_h,
            etiqueta_der,
            fila_h,
            gris
        )

        # Valor derecha

        draw_box(
            izquierda
            + etiqueta_izq
            + valor_izq
            + etiqueta_der,
            y - fila_h,
            valor_der,
            fila_h,
            amarillo
            if der_lab == 'ABONO'
            else None
        )

        # TEXTOS IZQUIERDA

        draw_multiline(
            izq_lab,
            izquierda,
            y,
            etiqueta_izq,
            fila_h,
            5.2,
            True,
            False,
            azul
        )

        draw_multiline(
            izq_val,
            izquierda + etiqueta_izq,
            y,
            valor_izq,
            fila_h,
            5.4,
            False,
            True
        )

        # TEXTOS DERECHA

        if der_lab:

            draw_multiline(
                der_lab,
                izquierda
                + etiqueta_izq
                + valor_izq,
                y,
                etiqueta_der,
                fila_h,
                5,
                True,
                True,
                azul
            )

        if der_lab == 'ABONO':

            draw_multiline(
                format_money(total),
                izquierda
                + etiqueta_izq
                + valor_izq
                + etiqueta_der,
                y,
                valor_der,
                fila_h,
                5.8,
                True,
                True,
                rojo
            )

        else:

            draw_multiline(
                der_valor,
                izquierda
                + etiqueta_izq
                + valor_izq
                + etiqueta_der,
                y,
                valor_der,
                fila_h,
                5.2,
                False,
                True
            )

        y -= fila_h

    y -= 2.5 * mm

    # =====================================================
    # request_detail
    # =====================================================

    cols = [
        18 * mm,   # ITEM
        68 * mm,   # CONCEPTO
        12 * mm,   # UNIDAD
        18 * mm,   # CANTIDAD
        25 * mm,   # COSTO
        19 * mm    # MONTO
    ]

    headers = [
        'ITEM',
        'CONCEPTO',
        'UNIDAD',
        'CANTIDAD',
        'COSTO UNITARIO S/',
        'MONTO'
    ]

    xs = [izquierda]

    for w in cols[:-1]:

        xs.append(
            xs[-1] + w
        )

    head_h = 10 * mm

    for i, titulo in enumerate(headers):

        draw_box(
            xs[i],
            y - head_h,
            cols[i],
            head_h,
            gris
        )

        draw_multiline(
            titulo,
            xs[i],
            y,
            cols[i],
            head_h,
            5.2,
            True,
            True,
            azul
        )

    y -= head_h

    # =====================================================
    # FILAS
    # =====================================================

    for item in items:

        concepto = str(
            item['descripcion'] or ''
        )

        lineas_concepto = wrap_text(
            concepto,
            cols[1] - 4,
            5.2
        )

        row_h = max(
            8 * mm,
            (
                len(lineas_concepto)
                * 2.3 * mm
            )
            + 2 * mm
        )

        valores = [

            format_number(
                item['nro']
            ),

            item['descripcion'],

            item['unidad'],

            format_number(
                item['cantidad']
            ),

            format_money(
                item['costo']
            ),

            format_money(
                item['monto']
            )

        ]

        for i, valor_item in enumerate(valores):

            draw_box(
                xs[i],
                y - row_h,
                cols[i],
                row_h
            )

            draw_multiline(
                valor_item,
                xs[i],
                y,
                cols[i],
                row_h,
                5.1,
                False,
                i != 1
            )

        y -= row_h

    # =====================================================
    # TOTAL
    # =====================================================

    total_h = 7 * mm

    draw_box(
        izquierda,
        y - total_h,
        sum(cols[:-1]),
        total_h
    )

    draw_box(
        izquierda + sum(cols[:-1]),
        y - total_h,
        cols[-1],
        total_h,
        amarillo
    )

    pdf.setFont(
        'Helvetica-Bold',
        5.5
    )

    pdf.setFillColor(
        azul
    )

    pdf.drawRightString(
        izquierda
        + sum(cols[:-1])
        - 2 * mm,
        y - 4.4 * mm,
        'TOTAL S/.'
    )

    pdf.setFillColor(
        rojo
    )

    draw_centered(
        format_money(total),
        izquierda + sum(cols[:-1]),
        y - total_h / 2,
        cols[-1],
        5.6,
        True,
        rojo
    )

    y -= total_h + 2.5 * mm

    # =====================================================
    # OBSERVACIONES
    # =====================================================

    obs_h = 12 * mm

    draw_box(
        izquierda,
        y - obs_h,
        ancho_formato,
        obs_h
    )

    draw_left(
        'OBSERVACIONES:',
        izquierda + 1 * mm,
        y - 2.8 * mm,
        5.3,
        True
    )

    draw_multiline(
        t['observaciones'] or '',
        izquierda + 1 * mm,
        y - 4 * mm,
        ancho_formato - 2 * mm,
        obs_h - 4 * mm,
        4.9
    )

    y -= obs_h

    # =====================================================
    # APROBACIONES
    # =====================================================

    roles = [
        'Logística',
        'Administración',
        'Gerencia General'
    ]

    titulos_ap = [
        'LOGÍSTICA',
        'ADMINISTRACIÓN',
        'GERENCIA'
    ]

    approval_map = {
        a['rol']: a
        for a in approvals
    }

    ap_w = ancho_formato / 3

    ap_title_h = 6 * mm

    ap_body_h = 24 * mm

    for i, rol in enumerate(roles):

        x = (
            izquierda
            + i * ap_w
        )

        draw_box(
            x,
            y - ap_title_h,
            ap_w,
            ap_title_h
        )

        draw_centered(
            titulos_ap[i],
            x,
            y - ap_title_h / 2,
            ap_w,
            5.2,
            True
        )

        draw_box(
            x,
            y - ap_title_h - ap_body_h,
            ap_w,
            ap_body_h
        )

        a = approval_map.get(
            rol
        )

        if not a:
            continue

        if a['aprobado']:

            draw_centered(
                'APROBADO',
                x,
                y - ap_title_h - 7 * mm,
                ap_w,
                5.3,
                True,
                verde
            )

            draw_centered(
                a['nombre'],
                x,
                y - ap_title_h - 12 * mm,
                ap_w,
                4.9,
                True
            )

            fecha_ap = ''

            if a['fecha']:

                try:

                    fecha_ap = datetime.strptime(
                        a['fecha'],
                        '%Y-%m-%d %H:%M:%S'
                    ).strftime(
                        '%d/%m/%Y %H:%M'
                    )

                except:

                    fecha_ap = str(
                        a['fecha']
                    )

            draw_centered(
                fecha_ap,
                x,
                y - ap_title_h - 17 * mm,
                ap_w,
                4.5
            )

        else:

            draw_centered(
                'PENDIENTE DE V°B°',
                x,
                y - ap_title_h - 10 * mm,
                ap_w,
                5,
                True,
                naranja
            )

            draw_centered(
                a['nombre'],
                x,
                y - ap_title_h - 15 * mm,
                ap_w,
                4.6
            )

    # =====================================================
    # GUARDAR PRIMER PDF
    # =====================================================

    pdf.save()

    formato_buffer.seek(0)

    # =====================================================
    # UNIR ADJUNTOS
    # =====================================================

    writer = PdfWriter()

    formato_reader = PdfReader(
        formato_buffer
    )

    for pagina in formato_reader.pages:

        writer.add_page(
            pagina
        )

    for adjunto in adjuntos:

        ruta = os.path.join(
            UPLOADS,
            adjunto['nombre_archivo']
        )

        if not os.path.exists(
            ruta
        ):
            continue

        try:

            lector = PdfReader(
                ruta
            )

            for pagina in lector.pages:

                writer.add_page(
                    pagina
                )

        except Exception as e:

            print(
                'No se pudo unir adjunto:',
                ruta,
                e
            )

    salida = io.BytesIO()

    writer.write(
        salida
    )

    salida.seek(0)

    # =====================================================
    # NOMBRE
    # =====================================================

    nombre_pdf = (
        f'SP LANR {t["numero"]} '
        f'{fecha_archivo}.pdf'
    )

    return salida, nombre_pdf


@app.route('/', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        name = request.form['username'].strip()
        pwd = request.form['password']
        c = db()
        u = c.execute(
            'SELECT * FROM users WHERE username = ? COLLATE BINARY',
            (name,)
        ).fetchone()
        c.close()
        if not u or not check_password_hash(u['password_hash'], pwd):
            flash('Usuario o contraseña incorrectos.', 'danger')
            return render_template('login.html')

        if not u['active']:
            flash('Tu cuenta está inactiva. Contacta a Sistemas.', 'danger')
            return render_template('login.html')

        session.permanent = True
        session['uid'] = u['id']
        return redirect(url_for('home'))
    return render_template('login.html')


@app.route('/logout')
def logout(): session.clear(); return redirect(url_for('login'))


@app.route('/notifications/<int:nid>')
@login_required
def open_notification(nid):

    u = user()
    c = db()

    n = c.execute(
        '''
        SELECT *
        FROM notificaciones
        WHERE id=?
        AND usuario_id=?
        ''',
        (nid, u['id'])
    ).fetchone()

    if not n:
        c.close()
        abort(404)

    c.execute(
        '''
        UPDATE notificaciones
        SET leida=1
        WHERE id=?
        ''',
        (nid,)
    )

    c.commit()

    tid = n['tramite_id']

    c.close()

    if tid:
        return redirect(
            url_for('request_detail', tid=tid)
        )

    return redirect(
        url_for('home')
    )


@app.route('/notifications/mark-all', methods=['POST'])
@login_required
def mark_all_notifications():

    u = user()
    c = db()

    c.execute(
        '''
        UPDATE notificaciones
        SET leida=1
        WHERE usuario_id=?
        ''',
        (u['id'],)
    )

    c.commit()
    c.close()

    return redirect(
        request.referrer or url_for('home')
    )


@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():

    if request.method == 'POST':

        email = request.form.get(
            'email',
            ''
        ).strip().lower()

        c = db()

        usuario = c.execute(
            '''
            SELECT *
            FROM users
            WHERE LOWER(email) = ?
            AND active = 1
            ''',
            (email,)
        ).fetchone()

        c.close()

        if usuario:

            token = generate_reset_token(usuario)

            reset_url = url_for(
                'reset_password',
                token=token,
                _external=True
            )

            try:
                send_password_reset_email(
                    usuario,
                    reset_url
                )

            except Exception as e:
                print(
                    'ERROR AL ENVIAR CORREO:',
                    e
                )

                flash(
                    'No se pudo enviar el correo. Contacta a Sistemas.',
                    'danger'
                )

                return render_template(
                    'forgot_password.html'
                )

        flash(
            'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
            'success'
        )

        return redirect(
            url_for('login')
        )

    return render_template(
        'forgot_password.html'
    )


@app.route(
    '/reset-password/<token>',
    methods=['GET', 'POST']
)
def reset_password(token):

    usuario = verify_reset_token(token)

    if not usuario:

        flash(
            'El enlace de recuperación es inválido o ha vencido.',
            'danger'
        )

        return redirect(
            url_for('forgot_password')
        )

    if request.method == 'POST':

        nueva = request.form.get(
            'new_password',
            ''
        )

        confirmar = request.form.get(
            'confirm_password',
            ''
        )

        if len(nueva) < 8:

            flash(
                'La contraseña debe tener al menos 8 caracteres.',
                'danger'
            )

            return render_template(
                'reset_password.html',
                token=token
            )

        if nueva != confirmar:

            flash(
                'Las contraseñas no coinciden.',
                'danger'
            )

            return render_template(
                'reset_password.html',
                token=token
            )

        if check_password_hash(
            usuario['password_hash'],
            nueva
        ):

            flash(
                'La nueva contraseña debe ser diferente a la contraseña actual.',
                'danger'
            )

            return render_template(
                'reset_password.html',
                token=token
            )

        c = db()

        c.execute(
            '''
            UPDATE users
            SET password_hash=?
            WHERE id=?
            ''',
            (
                generate_password_hash(nueva),
                usuario['id']
            )
        )

        c.commit()
        c.close()

        flash(
            'Contraseña restablecida correctamente. Ya puedes iniciar sesión.',
            'success'
        )

        return redirect(
            url_for('login')
        )

    return render_template(
        'reset_password.html',
        token=token
    )


@app.route('/change-password', methods=['GET', 'POST'])
@login_required
def change_password():
    u = user()

    if request.method == 'POST':
        actual = request.form['current']
        nueva = request.form['new']
        conf = request.form['confirm']

        if not check_password_hash(u['password_hash'], actual):
            flash('Contraseña actual incorrecta.', 'danger')

        elif nueva != conf:
            flash('Las contraseñas no coinciden.', 'danger')

        elif len(nueva) < 6:
            flash('Usa al menos 6 caracteres.', 'danger')

        elif nueva == actual:
            flash(
                'La nueva contraseña debe ser diferente a la contraseña actual.', 'danger')

        else:
            c = db()

            c.execute(
                'update users set password_hash=? where id=?',
                (generate_password_hash(nueva), u['id'])
            )

            c.commit()
            c.close()

            flash('Contraseña actualizada correctamente.', 'success')

            return redirect(url_for('home'))

    return render_template(
        'change_password.html',
        user=None
    )


@app.route('/home')
@login_required
def home():
    u = user()
    c = db()

    username = u['username'].lower()

    # ============================
    # CONTADORES
    # ============================

    if username in ('amoreno', 'jbecerra', 'ogonzales'):

        activos = c.execute(
            '''
            SELECT COUNT(*) c
            FROM tramites
            WHERE estado!='Cerrado'
            '''
        ).fetchone()['c']

    else:

        activos = c.execute(
            '''
            SELECT COUNT(*) c
            FROM tramites t

            WHERE t.estado!='Cerrado'

            AND (
                t.tipo != 'REQ'

                OR

                (
                    t.tipo='REQ'
                    AND (
                        SELECT COUNT(*)
                        FROM approvals a
                        WHERE a.tramite_id=t.id
                        AND a.aprobado=1
                    ) = 2
                )
            )
            '''
        ).fetchone()['c']

    counts = {
        'activos': activos,

        'aprob': c.execute(
            '''
            SELECT COUNT(*) c
            FROM approvals
            WHERE usuario_id=?
            AND aprobado=0
            ''',
            (u['id'],)
        ).fetchone()['c'],

        'oficina': c.execute(
            '''
            SELECT COUNT(*) c
            FROM tramites
            WHERE estado IN (
                'En oficina',
                'Cotización',
                'Comprado'
            )
            '''
        ).fetchone()['c'],

        'recibir': c.execute(
            '''
            SELECT COUNT(*) c
            FROM tramites
            WHERE estado='Enviado a obra'
            '''
        ).fetchone()['c']
    }

    # ============================
    # PENDIENTES DE APROBACIÓN
    # ============================

    pendientes_aprobacion = c.execute(
        '''
        SELECT
            t.id,
            t.tracking,
            t.tipo,
            t.subtipo,
            t.numero,
            t.fecha,
            t.estado,
            creador.full_name AS creador_nombre,
            a.rol AS rol_aprobacion

        FROM approvals a

        JOIN tramites t
            ON t.id = a.tramite_id

        JOIN users creador
            ON creador.id = t.creador

        WHERE a.usuario_id = ?
        AND a.aprobado = 0

        ORDER BY t.id DESC
        ''',
        (u['id'],)
    ).fetchall()

    # ============================================
    # PENDIENTES DE GESTIÓN LOGÍSTICA - RODRIGO
    # ============================================

    pendientes_logistica = []

    if username == 'rvasquez':

        pendientes_logistica = c.execute(
            '''
            SELECT
                t.id,
                t.tracking,
                t.numero,
                t.fecha,
                t.estado,
                u.full_name AS creador_nombre,

                g.recibido_fecha,
                g.cotizacion_estado,
                g.proveedor,
                g.nro_comprobante,
                g.monto,
                g.estado_pago,
                g.guia_numero,
                g.guia_pendiente,
                g.enviado_fecha,

                CASE

                    WHEN t.estado = 'Aprobado'
                        THEN 'Recepcionar'

                    WHEN t.estado = 'Recibido por Logística'
                        THEN 'Cotización / compra'

                    WHEN t.estado = 'En gestión de compra'
                        AND (
                            g.estado_pago IS NULL
                            OR g.estado_pago = ''
                        )
                        THEN 'Pago'

                    WHEN g.estado_pago = 'Pendiente de Tesorería'
                        THEN 'Pago en Tesorería'

                    WHEN g.estado_pago IN (
                        'Pagado por Logística',
                        'Pagado por Tesorería'
                    )
                    AND t.estado NOT IN (
                        'Enviado a obra',
                        'Recibido en obra',
                        'Cerrado'
                    )
                        THEN 'Guía y envío a obra'

                    ELSE 'Revisar'

                END AS pendiente_de

            FROM tramites t

            JOIN users u
                ON u.id = t.creador

            LEFT JOIN gestion_logistica g
                ON g.tramite_id = t.id

            WHERE t.tipo = 'REQ'

            AND t.estado IN (
                'Aprobado',
                'Recibido por Logística',
                'En gestión de compra'
            )

            ORDER BY t.id DESC
            '''
        ).fetchall()

    pendientes_envio = 0

    if username == 'rvasquez':
        pendientes_envio = sum(
            1
            for x in pendientes_logistica
            if x['pendiente_de'] == 'Guía y envío a obra'
        )

    # ============================================
    # PENDIENTES DE TESORERÍA - YOANA
    # ============================================

    pendientes_tesoreria = []
    sp_tesoreria = []

    if username == 'ycoronado':

        # ----------------------------------------
        # REQ: pagos y reembolsos
        # ----------------------------------------

        pendientes_tesoreria = c.execute(
            '''
            SELECT
                s.id AS solicitud_id,
                s.tramite_id,
                s.origen,
                s.monto,
                s.motivo,
                s.estado,
                s.fecha_solicitud,

                t.tracking,
                t.numero,
                t.tipo,
                t.estado AS estado_tramite,

                creador.full_name AS creador_nombre

            FROM solicitudes_tesoreria s

            JOIN tramites t
                ON t.id = s.tramite_id

            JOIN users creador
                ON creador.id = t.creador

            WHERE s.estado = 'Pendiente'

            AND s.origen IN (
                'REQ-Compra',
                'REQ-Reembolso'
            )

            ORDER BY s.id DESC
            '''
        ).fetchall()

        # ----------------------------------------
        # SP: asignadas por Gerencia General
        # ----------------------------------------

        sp_tesoreria = c.execute(
            '''
            SELECT
                t.id,
                t.tracking,
                t.numero,
                t.fecha,
                t.estado,
                t.abono,

                creador.full_name AS creador_nombre,

                g.fecha_asignacion

            FROM tramites t

            JOIN users creador
                ON creador.id = t.creador

            JOIN gestion_sp g
                ON g.tramite_id = t.id

            WHERE t.tipo = 'SP'

            AND g.asignado_pago = 'Tesorería'

            AND t.estado = 'Asignada a Tesorería'

            ORDER BY t.id DESC
            '''
        ).fetchall()

    # ============================================
    # PENDIENTES DE GERENCIA GENERAL - LUIS
    # ============================================

    sp_pendientes_asignacion = []
    sp_pendientes_conformidad = []

    if username == 'lneyra':

        # SP que ya tienen los V°B° de Administración y Logística
        # y esperan que Gerencia General decida quién realizará el pago.

        sp_pendientes_asignacion = c.execute(
            '''
            SELECT
                t.id,
                t.tracking,
                t.numero,
                t.fecha,
                t.estado,
                t.abono,
                creador.full_name AS creador_nombre

            FROM tramites t

            JOIN users creador
                ON creador.id = t.creador

            WHERE t.tipo = 'SP'
            AND t.estado = 'Pendiente asignación de pago'

            ORDER BY t.id DESC
            '''
        ).fetchall()

        # SP cuyo pago ya fue realizado y están esperando
        # la conformidad final de Gerencia General.

        sp_pendientes_conformidad = c.execute(
            '''
            SELECT
                t.id,
                t.tracking,
                t.numero,
                t.fecha,
                t.estado,
                t.abono,
                creador.full_name AS creador_nombre,

                g.medio_pago,
                g.monto_pagado,
                g.fecha_pago

            FROM tramites t

            JOIN users creador
                ON creador.id = t.creador

            JOIN gestion_sp g
                ON g.tramite_id = t.id

            WHERE t.tipo = 'SP'
            AND t.estado = 'Pagada pendiente conformidad GG'
            AND g.conformidad_gg = 0

            ORDER BY t.id DESC
            '''
        ).fetchall()

    c.close()

    return render_template(
        'home.html',
        user=u,
        counts=counts,
        pendientes_aprobacion=pendientes_aprobacion,
        pendientes_logistica=pendientes_logistica,
        pendientes_envio=pendientes_envio,
        pendientes_tesoreria=pendientes_tesoreria,
        sp_tesoreria=sp_tesoreria,
        sp_pendientes_asignacion=sp_pendientes_asignacion,
        sp_pendientes_conformidad=sp_pendientes_conformidad
    )


@app.route('/requests/new')
@login_required
def new_request():
    u = user()

    if not can_create_request(u):
        abort(403)

    return render_template(
        'new_request.html',
        user=u,
        project=PROJECT,
        place=PLACE
    )


@app.route('/requirements/create', methods=['POST'])
@login_required
def create_requirement():
    u = user()

    if not can_create_request(u):
        abort(403)

    numero = request.form['numero'].strip()
    fecha = request.form['fecha']
    year = fecha[:4]
    tracking = next_code('REQ', year)
    primero = re.sub(r'\D', '', numero.split('-')[0]) or numero.split('-')[0]
    formato = 'F01A-LANR-'+primero
    c = db()
    cur = c.execute('INSERT INTO tramites(tracking,tipo,numero,fecha,proyecto,lugar,estado,formato,creador,creado) values(?,?,?,?,?,?,?,?,?,?)',
                    (tracking, 'REQ', numero, fecha, PROJECT, PLACE, 'Pendiente de aprobación', formato, u['id'], datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
    tid = cur.lastrowid
    secs = request.form.getlist('seccion[]')
    desc = request.form.getlist('descripcion[]')
    qty = request.form.getlist('cantidad[]')
    und = request.form.getlist('unidad[]')
    stock = request.form.getlist('stock[]')
    just = request.form.getlist('justificacion[]')
    cnt = {}
    for i, d in enumerate(desc):
        if not d.strip():
            continue
        s = secs[i]
        cnt[s] = cnt.get(s, 0)+1
        q = float(qty[i] or 0)
        st = float(stock[i] or 0)
        c.execute('insert into items(tramite_id,seccion,nro,descripcion,unidad,cantidad,stock,comprar,justificacion) values(?,?,?,?,?,?,?,?,?)',
                  (tid, s, cnt[s], d, und[i], q, st, max(q-st, 0), just[i]))
    jl = c.execute(
        "select id from users where username='jBecerra'"
    ).fetchone()['id']

    art = c.execute(
        "select id from users where username='aMoreno'"
    ).fetchone()['id']

    for rol, uid in [
        ('Planeamiento', jl),
        ('Gerencia de Obra', art)
    ]:
        c.execute(
            '''
            INSERT INTO approvals(
                tramite_id,
                rol,
                usuario_id
            )
            VALUES(?,?,?)
            ''',
            (tid, rol, uid)
        )
    c.execute('insert into history(tramite_id,usuario_id,accion,fecha) values(?,?,?,?)', (tid,
              u['id'], 'Requerimiento registrado: '+tracking, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))
    c.commit()
    c.close()
    return redirect(url_for('request_detail', tid=tid))


@app.route('/payment-requests/create', methods=['POST'])
@login_required
def create_payment_request():
    u = user()

    if not can_create_request(u):
        abort(403)

    numero = request.form['numero'].strip()
    fecha = request.form['fecha']
    subtipo = request.form['subtipo']
    beneficiario = request.form['beneficiario'].strip()

    dni_ruc = request.form.get('dni_ruc', '').strip()
    modalidad_pago = request.form.get('modalidad_pago', '').strip()
    responsable = request.form.get('responsable', '').strip()
    tipo_comprobante = request.form.get('tipo_comprobante', '').strip()
    banco = request.form.get('banco', '').strip()
    nro_comprobante = request.form.get('nro_comprobante', '').strip()
    cuenta_cci = request.form.get('cuenta_cci', '').strip()
    observaciones = request.form.get('observaciones', '').strip()

    tracking = next_code('SP', fecha[:4])

    c = db()

    cur = c.execute(
        '''
        INSERT INTO tramites(
            tracking,
            tipo,
            subtipo,
            numero,
            fecha,
            proyecto,
            lugar,
            beneficiario,
            dni_ruc,
            modalidad_pago,
            responsable,
            tipo_comprobante,
            banco,
            nro_comprobante,
            cuenta_cci,
            abono,
            observaciones,
            estado,
            creador,
            creado
        )
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ''',
        (
            tracking,
            'SP',
            subtipo,
            numero,
            fecha,
            PROJECT,
            PLACE,
            beneficiario,
            dni_ruc,
            modalidad_pago,
            responsable,
            tipo_comprobante,
            banco,
            nro_comprobante,
            cuenta_cci,
            0,
            observaciones,
            'Pendiente de aprobación',
            u['id'],
            datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        )
    )

    tid = cur.lastrowid

    # ===============================
    # ADJUNTOS
    # ===============================

    archivos = request.files.getlist('documentos')
    orden = 1

    for archivo in archivos:

        if not archivo or not archivo.filename:
            continue

        if not archivo.filename.lower().endswith('.pdf'):
            continue

        nombre_original = archivo.filename

        nombre_seguro = secure_filename(
            f'{tid}_{orden}_{archivo.filename}'
        )

        ruta = os.path.join(
            UPLOADS,
            nombre_seguro
        )

        archivo.save(ruta)

        c.execute(
            '''
            INSERT INTO attachments(
                tramite_id,
                nombre_original,
                nombre_archivo,
                orden,
                fecha
            )
            VALUES(?,?,?,?,?)
            ''',
            (
                tid,
                nombre_original,
                nombre_seguro,
                orden,
                datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            )
        )

        orden += 1

    # ===============================
    # ÍTEMS
    # ===============================

    conceptos = request.form.getlist('concepto[]')
    unidades = request.form.getlist('unidad_sp[]')
    cantidades = request.form.getlist('cantidad_sp[]')
    costos = request.form.getlist('costo[]')

    for i, concepto in enumerate(conceptos):

        if not concepto.strip():
            continue

        cantidad = parse_decimal(cantidades[i])
        costo = parse_decimal(costos[i])

        c.execute(
            '''
            INSERT INTO items(
                tramite_id,
                nro,
                descripcion,
                unidad,
                cantidad,
                costo,
                monto
            )
            VALUES(?,?,?,?,?,?,?)
            ''',
            (
                tid,
                i + 1,
                concepto,
                unidades[i],
                cantidad,
                costo,
                cantidad * costo
            )
        )

    # TOTAL AUTOMÁTICO DE LA SOLICITUD
    total_solicitud = c.execute(
        '''
        SELECT COALESCE(SUM(monto), 0)
        FROM items
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()[0]

    c.execute(
        '''
        UPDATE tramites
        SET abono=?
        WHERE id=?
        ''',
        (
            total_solicitud,
            tid
        )
    )

    # ===============================
    # APROBACIONES
    # ===============================

    for username, rol in [
        ('rVasquez', 'Logística'),
        ('sSanchez', 'Administración')
    ]:

        uid = c.execute(
            '''
            SELECT id
            FROM users
            WHERE username=? COLLATE NOCASE
            ''',
            (username,)
        ).fetchone()['id']

        c.execute(
            '''
            INSERT INTO approvals(
                tramite_id,
                rol,
                usuario_id
            )
            VALUES(?,?,?)
            ''',
            (tid, rol, uid)
        )

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Solicitud registrada: ' + tracking,
            datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        )
    )

    c.commit()
    c.close()

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/requests')
@login_required
def requests():
    u = user()
    c = db()

    username = u['username'].lower()

    if username in ('amoreno', 'jbecerra', 'ogonzales'):

        ts = c.execute(
            '''
            SELECT t.*, u.full_name creador_nombre
            FROM tramites t
            JOIN users u ON u.id=t.creador
            ORDER BY t.id DESC
            '''
        ).fetchall()

    else:

        ts = c.execute(
            '''
            SELECT t.*, u.full_name creador_nombre
            FROM tramites t
            JOIN users u ON u.id=t.creador

            WHERE
                t.tipo != 'REQ'

                OR

                (
                    t.tipo='REQ'
                    AND (
                        SELECT COUNT(*)
                        FROM approvals a
                        WHERE a.tramite_id=t.id
                        AND a.aprobado=1
                    ) = 2
                )

            ORDER BY t.id DESC
            '''
        ).fetchall()

    c.close()

    return render_template(
        'requests.html',
        user=u,
        requests=ts
    )


@app.route('/requests/search')
@login_required
def search_request():
    u = user()

    if u['username'].lower() not in (
        'amoreno',
        'jbecerra',
        'ogonzales'
    ):
        abort(403)

    return render_template(
        'search_request.html',
        user=u
    )


@app.route('/api/requests/search')
@login_required
def api_search_requests():
    u = user()

    if u['username'].lower() not in (
        'amoreno',
        'jbecerra',
        'ogonzales'
    ):
        abort(403)

    tipo = request.args.get('tipo', 'REQ').strip().upper()
    texto = request.args.get('q', '').strip()

    if tipo not in ('REQ', 'SP'):
        tipo = 'REQ'

    if not texto:
        return jsonify([])

    c = db()

    resultados = c.execute(
        '''
        SELECT
            t.id,
            t.tracking,
            t.numero,
            t.tipo,
            t.subtipo,
            t.estado,
            t.fecha,
            u.full_name AS creador_nombre

        FROM tramites t

        JOIN users u
            ON u.id = t.creador

        WHERE t.tipo = ?

        AND (
            t.tracking LIKE ?
            OR t.numero LIKE ?
        )

        ORDER BY t.id DESC

        LIMIT 15
        ''',
        (
            tipo,
            f'%{texto}%',
            f'%{texto}%'
        )
    ).fetchall()

    c.close()

    return jsonify([
        {
            'id': r['id'],
            'tracking': r['tracking'],
            'numero': r['numero'],
            'tipo': r['tipo'],
            'subtipo': r['subtipo'],
            'estado': r['estado'],
            'fecha': r['fecha'],
            'creador': r['creador_nombre']
        }
        for r in resultados
    ])


@app.route('/requests/<int:tid>')
@login_required
def request_detail(tid):
    u = user()
    c = db()

    t = c.execute(
        '''
        SELECT t.*, u.full_name creador_nombre
        FROM tramites t
        JOIN users u ON u.id=t.creador
        WHERE t.id=?
        ''',
        (tid,)
    ).fetchone()

    items = c.execute(
        '''
        SELECT *
        FROM items
        WHERE tramite_id=?
        ORDER BY seccion,nro
        ''',
        (tid,)
    ).fetchall()

    aps = c.execute(
        '''
        SELECT a.*, u.full_name nombre
        FROM approvals a
        JOIN users u ON u.id=a.usuario_id
        WHERE a.tramite_id=?
        ''',
        (tid,)
    ).fetchall()

    hs = c.execute(
        '''
        SELECT h.*, u.full_name nombre
        FROM history h
        JOIN users u ON u.id=h.usuario_id
        WHERE h.tramite_id=?
        ORDER BY h.id DESC
        ''',
        (tid,)
    ).fetchall()

    adjuntos = c.execute(
        '''
        SELECT *
        FROM attachments
        WHERE tramite_id=?
        ORDER BY orden,id
        ''',
        (tid,)
    ).fetchall()

    gestion = c.execute(
        '''
        SELECT *
        FROM gestion_logistica
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    archivos_logistica = c.execute(
        '''
        SELECT *
        FROM archivos_logistica
        WHERE tramite_id=?
        ORDER BY id DESC
        ''',
        (tid,)
    ).fetchall()

    tesoreria = c.execute(
        '''
        SELECT *
        FROM solicitudes_tesoreria
        WHERE tramite_id=?
        ORDER BY id DESC
        ''',
        (tid,)
    ).fetchall()

    regularizaciones = c.execute(
        '''
        SELECT
            r.*,
            u.full_name responsable_nombre
        FROM regularizaciones r

        LEFT JOIN users u
            ON u.id = r.responsable_id

        WHERE r.tramite_id=?

        ORDER BY
            CASE
                WHEN r.estado='Pendiente'
                THEN 0
                ELSE 1
            END,
            r.id DESC
        ''',
        (tid,)
    ).fetchall()

    gestion_sp = c.execute(
        '''
        SELECT *
        FROM gestion_sp
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    fecha_hoy = datetime.now().strftime('%Y-%m-%d')

    c.close()

    return render_template(
        'request_detail.html',
        user=u,
        t=t,
        items=items,
        approvals=aps,
        history=hs,
        attachments=adjuntos,
        gestion=gestion,
        archivos_logistica=archivos_logistica,
        tesoreria=tesoreria,
        regularizaciones=regularizaciones,
        gestion_sp=gestion_sp,
        fecha_hoy=fecha_hoy
    )


@app.route('/logistics/files/<int:aid>/view')
@login_required
def view_logistics_file(aid):

    c = db()

    archivo = c.execute(
        '''
        SELECT *
        FROM archivos_logistica
        WHERE id=?
        ''',
        (aid,)
    ).fetchone()

    c.close()

    if not archivo:
        abort(404)

    return send_from_directory(
        UPLOADS,
        archivo['nombre_archivo'],
        as_attachment=False,
        download_name=archivo['nombre_original']
    )


@app.route('/logistics/files/<int:aid>/download')
@login_required
def download_logistics_file(aid):

    c = db()

    archivo = c.execute(
        '''
        SELECT *
        FROM archivos_logistica
        WHERE id=?
        ''',
        (aid,)
    ).fetchone()

    c.close()

    if not archivo:
        abort(404)

    return send_from_directory(
        UPLOADS,
        archivo['nombre_archivo'],
        as_attachment=True,
        download_name=archivo['nombre_original']
    )


@app.route('/attachments/<int:aid>/view')
@login_required
def view_attachment(aid):
    c = db()

    adjunto = c.execute(
        'SELECT * FROM attachments WHERE id=?',
        (aid,)
    ).fetchone()

    c.close()

    if not adjunto:
        abort(404)

    return send_from_directory(
        UPLOADS,
        adjunto['nombre_archivo'],
        as_attachment=False,
        download_name=adjunto['nombre_original']
    )


@app.route('/attachments/<int:aid>/download')
@login_required
def download_attachment(aid):
    c = db()

    adjunto = c.execute(
        'SELECT * FROM attachments WHERE id=?',
        (aid,)
    ).fetchone()

    c.close()

    if not adjunto:
        abort(404)

    return send_from_directory(
        UPLOADS,
        adjunto['nombre_archivo'],
        as_attachment=True,
        download_name=adjunto['nombre_original']
    )


@app.route('/requests/<int:tid>/pdf')
@login_required
def view_requirement_pdf(tid):

    buffer, nombre_pdf = generate_requirement_pdf(tid)

    return send_file(
        buffer,
        mimetype='application/pdf',
        as_attachment=False,
        download_name=nombre_pdf
    )


@app.route('/requests/<int:tid>/pdf/download')
@login_required
def download_requirement_pdf(tid):

    buffer, nombre_pdf = generate_requirement_pdf(tid)

    return send_file(
        buffer,
        mimetype='application/pdf',
        as_attachment=True,
        download_name=nombre_pdf
    )


@app.route('/requests/<int:tid>/payment-pdf')
@login_required
def view_payment_request_pdf(tid):

    buffer, nombre_pdf = generate_payment_request_pdf(tid)

    return send_file(
        buffer,
        mimetype='application/pdf',
        as_attachment=False,
        download_name=nombre_pdf
    )


@app.route('/requests/<int:tid>/payment-pdf/download')
@login_required
def download_payment_request_pdf(tid):

    buffer, nombre_pdf = generate_payment_request_pdf(tid)

    return send_file(
        buffer,
        mimetype='application/pdf',
        as_attachment=True,
        download_name=nombre_pdf
    )


@app.route('/approvals/<int:aid>/approve', methods=['POST'])
@login_required
def approve(aid):
    u = user()
    c = db()

    a = c.execute(
        'SELECT * FROM approvals WHERE id=?',
        (aid,)
    ).fetchone()

    if not a or (
        a['usuario_id'] != u['id']
        and u['role'] != 'Sistemas'
    ):
        abort(403)

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute(
        '''
        UPDATE approvals
        SET aprobado=1, fecha=?
        WHERE id=?
        ''',
        (now, aid)
    )

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            a['tramite_id'],
            u['id'],
            'Visto bueno: ' + a['rol'],
            now
        )
    )

    pend = c.execute(
        '''
        SELECT COUNT(*) c
        FROM approvals
        WHERE tramite_id=?
        AND aprobado=0
        ''',
        (a['tramite_id'],)
    ).fetchone()['c']

    req_aprobado_para_notificar = False
    sp_listo_para_asignar = False

    if pend == 0:

        t = c.execute(
            '''
            SELECT *
            FROM tramites
            WHERE id=?
            ''',
            (a['tramite_id'],)
        ).fetchone()

        if t['tipo'] == 'REQ':

            c.execute(
                '''
                UPDATE tramites
                SET estado='Aprobado'
                WHERE id=?
                ''',
                (a['tramite_id'],)
            )
            req_aprobado_para_notificar = True

        elif t['tipo'] == 'SP':

            c.execute(
                '''
                UPDATE tramites
                SET estado='Pendiente asignación de pago'
                WHERE id=?
                ''',
                (a['tramite_id'],)
            )

            sp_listo_para_asignar = True

    c.commit()

    tid = a['tramite_id']

    notificaciones_pendientes = []

    if req_aprobado_para_notificar:

        destinatarios = c.execute(
            '''
            SELECT id, username
            FROM users
            WHERE LOWER(username) IN (
                'rvasquez',
                'ssanchez',
                'lneyra',
                'ycoronado'
            )
            '''
        ).fetchall()

        for destinatario in destinatarios:

            username_destino = destinatario['username'].lower()

            if username_destino == 'rvasquez':
                notificaciones_pendientes.append(
                    (
                        destinatario['id'],
                        tid,
                        'new_request requerimiento para atención',
                        f"El requerimiento {t['tracking']} fue aprobado y está listo para ser atendido por Logística.",
                        'accion'
                    )
                )

            else:
                notificaciones_pendientes.append(
                    (
                        destinatario['id'],
                        tid,
                        'Requerimiento aprobado',
                        f"El requerimiento {t['tracking']} completó sus V°B° y pasó a gestión de Logística.",
                        'informativa'
                    )
                )

    if sp_listo_para_asignar:

        luis = c.execute(
            '''
            SELECT id
            FROM users
            WHERE LOWER(username)='lneyra'
            '''
        ).fetchone()

        if luis:

            notificaciones_pendientes.append(
                (
                    luis['id'],
                    tid,
                    'Solicitud lista para asignar pago',
                    f"La solicitud {t['tracking']} completó los V°B° de Administración y Logística. Debes revisar el expediente y decidir quién realizará el pago.",
                    'accion'
                )
            )

    c.close()

    for datos_notificacion in notificaciones_pendientes:
        notify(*datos_notificacion)

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/sp/<int:tid>/assign-payment', methods=['POST'])
@login_required
def assign_sp_payment(tid):

    u = user()

    # Solo Gerencia General
    if u['username'].lower() != 'lneyra':
        abort(403)

    asignado = request.form.get(
        'asignado_pago',
        ''
    ).strip()

    if asignado not in (
        'Tesorería',
        'Gerencia General'
    ):
        flash(
            'Selecciona quién realizará el pago.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        ''',
        (tid,)
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if t['tipo'] != 'SP':
        c.close()
        abort(400)

    if t['estado'] != 'Pendiente asignación de pago':
        c.close()

        flash(
            'Esta solicitud no está pendiente de asignación.',
            'warning'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    # ==========================================
    # CREAR GESTIÓN DEL SP SI NO EXISTE
    # ==========================================

    c.execute(
        '''
        INSERT OR IGNORE INTO gestion_sp(
            tramite_id
        )
        VALUES(?)
        ''',
        (tid,)
    )

    # ==========================================
    # GUARDAR QUIÉN PAGARÁ
    # ==========================================

    c.execute(
        '''
        UPDATE gestion_sp
        SET
            asignado_pago=?,
            asignado_por=?,
            fecha_asignacion=?
        WHERE tramite_id=?
        ''',
        (
            asignado,
            u['id'],
            now,
            tid
        )
    )

    # ==========================================
    # CAMBIAR ESTADO DEL SP
    # ==========================================

    if asignado == 'Tesorería':

        new_request_estado = 'Asignada a Tesorería'

        accion = (
            'Pago asignado a Tesorería'
        )

    else:

        new_request_estado = (
            'Asignada a Gerencia General'
        )

        accion = (
            'Pago asumido por Gerencia General'
        )

    c.execute(
        '''
        UPDATE tramites
        SET estado=?
        WHERE id=?
        ''',
        (
            new_request_estado,
            tid
        )
    )

    # ==========================================
    # HISTORIAL
    # ==========================================

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            accion,
            now
        )
    )

    c.commit()

    # ==========================================
    # BUSCAR A YOANA SI FUE ASIGNADA
    # ==========================================

    yoana_id = None

    if asignado == 'Tesorería':

        yoana = c.execute(
            '''
            SELECT id
            FROM users
            WHERE LOWER(username)='ycoronado'
            '''
        ).fetchone()

        if yoana:
            yoana_id = yoana['id']

    tracking = t['tracking']

    c.close()

    # ==========================================
    # NOTIFICACIÓN
    # ==========================================

    if yoana_id:

        notify(
            yoana_id,
            tid,
            'Solicitud asignada para pago',
            f"La solicitud {tracking} fue asignada a Tesorería. Revisa el expediente y registra el pago.",
            'accion'
        )

    flash(
        'Responsable del pago asignado correctamente.',
        'success'
    )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/sp/<int:tid>/register-payment', methods=['POST'])
@login_required
def register_sp_payment(tid):

    u = user()
    username = u['username'].lower()

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        ''',
        (tid,)
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if t['tipo'] != 'SP':
        c.close()
        abort(400)

    gestion_sp = c.execute(
        '''
        SELECT *
        FROM gestion_sp
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    if not gestion_sp:
        c.close()
        abort(400)

    # ==========================================
    # VALIDAR QUIÉN TIENE DERECHO A PAGAR
    # ==========================================

    asignado = gestion_sp['asignado_pago']

    puede_pagar = False

    if (
        asignado == 'Tesorería'
        and t['estado'] == 'Asignada a Tesorería'
    ):
        puede_pagar = username == 'ycoronado'

    elif (
        asignado == 'Gerencia General'
        and t['estado'] == 'Asignada a Gerencia General'
    ):
        puede_pagar = username == 'lneyra'

    if not puede_pagar:
        c.close()
        abort(403)

    # ==========================================
    # EVITAR PAGO DUPLICADO
    # ==========================================

    if gestion_sp['pagado_por']:

        c.close()

        flash(
            'Esta solicitud ya tiene un pago registrado.',
            'warning'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    medio_pago = request.form.get(
        'medio_pago',
        ''
    ).strip()

    banco_pago = request.form.get(
        'banco_pago',
        ''
    ).strip()

    fecha_pago = request.form.get(
        'fecha_pago',
        ''
    ).strip()

    monto_pagado = parse_decimal(
        request.form.get('monto_pagado')
    )

    nro_operacion = request.form.get(
        'nro_operacion',
        ''
    ).strip()

    comprobante = request.files.get(
        'comprobante_pago'
    )

    # ==========================================
    # VALIDACIONES
    # ==========================================

    if medio_pago not in (
        'Transferencia',
        'Depósito',
        'Efectivo',
        'Yape',
        'Plin',
        'Otro'
    ):

        c.close()

        flash(
            'Selecciona un medio de pago válido.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    if not fecha_pago:

        c.close()

        flash(
            'Ingresa la fecha del pago.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    if monto_pagado <= 0:

        c.close()

        flash(
            'El monto pagado no es válido.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # El pago debe corresponder al monto del SP
    monto_solicitado = parse_decimal(
        t['abono']
    )

    if abs(monto_pagado - monto_solicitado) > 0.01:

        c.close()

        flash(
            f'El monto pagado debe ser S/ {monto_solicitado:,.2f}.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # Comprobante obligatorio
    if not comprobante or not comprobante.filename:

        c.close()

        flash(
            'Debes adjuntar el comprobante de pago.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    permitidos = (
        '.pdf',
        '.jpg',
        '.jpeg',
        '.png',
        '.webp'
    )

    if not comprobante.filename.lower().endswith(
        permitidos
    ):

        c.close()

        flash(
            'El comprobante debe ser PDF o imagen.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # ==========================================
    # GUARDAR COMPROBANTE
    # ==========================================

    nombre_original = comprobante.filename

    nombre_archivo = secure_filename(
        f"SP_PAGO_{tid}_{int(datetime.now().timestamp())}_{comprobante.filename}"
    )

    comprobante.save(
        os.path.join(
            UPLOADS,
            nombre_archivo
        )
    )

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    # ==========================================
    # REGISTRAR PAGO
    # ==========================================

    c.execute(
        '''
        UPDATE gestion_sp
        SET
            pagado_por=?,
            medio_pago=?,
            banco_pago=?,
            nro_operacion=?,
            monto_pagado=?,
            fecha_pago=?,
            nombre_original_pago=?,
            nombre_archivo_pago=?
        WHERE tramite_id=?
        ''',
        (
            u['id'],
            medio_pago,
            banco_pago,
            nro_operacion,
            monto_pagado,
            fecha_pago,
            nombre_original,
            nombre_archivo,
            tid
        )
    )

    # ==========================================
    # EL SP YA FUE PAGADO
    # ==========================================

    c.execute(
        '''
        UPDATE tramites
        SET estado='Pagada pendiente conformidad GG'
        WHERE id=?
        ''',
        (tid,)
    )

    # ==========================================
    # HISTORIAL
    # ==========================================

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Pago de solicitud registrado',
            now
        )
    )

    c.commit()

    # ==========================================
    # SI PAGÓ YOANA, AVISAR A LUIS
    # ==========================================

    luis_id = None

    if username == 'ycoronado':

        luis = c.execute(
            '''
            SELECT id
            FROM users
            WHERE LOWER(username)='lneyra'
            '''
        ).fetchone()

        if luis:
            luis_id = luis['id']

    tracking = t['tracking']

    c.close()

    if luis_id:

        notify(
            luis_id,
            tid,
            'Pago de solicitud realizado',
            f"La solicitud {tracking} ya fue pagada por Tesorería. Revisa el expediente y registra tu conformidad final.",
            'accion'
        )

    flash(
        'Pago registrado correctamente.',
        'success'
    )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/sp/<int:tid>/final-approval', methods=['POST'])
@login_required
def confirm_sp_payment(tid):

    u = user()
    username = u['username'].lower()

    # Solo Gerencia General
    if username != 'lneyra':
        abort(403)

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        ''',
        (tid,)
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if t['tipo'] != 'SP':
        c.close()
        abort(400)

    if t['estado'] != 'Pagada pendiente conformidad GG':

        c.close()

        flash(
            'Esta solicitud no está pendiente de conformidad final.',
            'warning'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    gestion_sp = c.execute(
        '''
        SELECT *
        FROM gestion_sp
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    if not gestion_sp:
        c.close()
        abort(400)

    # Debe existir un pago registrado
    if not gestion_sp['pagado_por']:

        c.close()

        flash(
            'La solicitud aún no tiene un pago registrado.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # Debe existir comprobante
    if not gestion_sp['nombre_archivo_pago']:

        c.close()

        flash(
            'No se puede finalizar sin comprobante de pago.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    # ==========================================
    # CONFORMIDAD FINAL
    # ==========================================

    c.execute(
        '''
        UPDATE gestion_sp
        SET
            conformidad_gg=1,
            fecha_conformidad=?
        WHERE tramite_id=?
        ''',
        (
            now,
            tid
        )
    )

    # ==========================================
    # FINALIZAR SP
    # ==========================================

    c.execute(
        '''
        UPDATE tramites
        SET estado='Finalizada'
        WHERE id=?
        ''',
        (tid,)
    )

    # ==========================================
    # HISTORIAL
    # ==========================================

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Conformidad final de Gerencia General. Solicitud finalizada.',
            now
        )
    )

    # ==========================================
    # DESTINATARIOS FINALES
    # ==========================================

    destinatarios = c.execute(
        '''
        SELECT id
        FROM users
        WHERE LOWER(username) IN (
            'jbecerra',
            'amoreno'
        )
        '''
    ).fetchall()

    tracking = t['tracking']

    c.commit()
    c.close()

    # ==========================================
    # NOTIFICACIONES
    # ==========================================

    for destinatario in destinatarios:

        notify(
            destinatario['id'],
            tid,
            'Solicitud de pago finalizada',
            f"La solicitud {tracking} fue pagada y cuenta con la conformidad final de Gerencia General.",
            'informativa'
        )

    flash(
        'Conformidad registrada. La solicitud fue finalizada.',
        'success'
    )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/sp/<int:tid>/payment-receipt/view')
@login_required
def view_sp_payment_receipt(tid):

    c = db()

    gestion_sp = c.execute(
        '''
        SELECT *
        FROM gestion_sp
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    c.close()

    if not gestion_sp:
        abort(404)

    if not gestion_sp['nombre_archivo_pago']:
        abort(404)

    return send_from_directory(
        UPLOADS,
        gestion_sp['nombre_archivo_pago'],
        as_attachment=False
    )


@app.route('/sp/<int:tid>/payment-receipt/download')
@login_required
def download_sp_payment_receipt(tid):

    c = db()

    gestion_sp = c.execute(
        '''
        SELECT *
        FROM gestion_sp
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    c.close()

    if not gestion_sp:
        abort(404)

    if not gestion_sp['nombre_archivo_pago']:
        abort(404)

    return send_from_directory(
        UPLOADS,
        gestion_sp['nombre_archivo_pago'],
        as_attachment=True,
        download_name=gestion_sp['nombre_original_pago']
    )


@app.route('/requests/<int:tid>/status', methods=['POST'])
@login_required
def update_request_status(tid):

    u = user()
    est = request.form.get('estado', '')
    uname = u['username'].lower()

    # Aquí únicamente se confirma la recepción final en obra
    if est != 'Recibido en obra':
        abort(403)

    if uname not in ('amoreno', 'jbecerra', 'ogonzales'):
        abort(403)

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        ''',
        (tid,)
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    # Solo puede recibirse algo que realmente fue enviado a obra
    if t['tipo'] != 'REQ' or t['estado'] != 'Enviado a obra':
        c.close()
        abort(400)

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute(
        '''
        UPDATE tramites
        SET estado='Cerrado'
        WHERE id=?
        ''',
        (tid,)
    )

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Recepción confirmada en obra. Requerimiento cerrado.',
            now
        )
    )

    c.commit()

    # ==========================================
    # NOTIFICACIÓN FINAL DEL REQUERIMIENTO
    # ==========================================

    destinatarios_final = c.execute(
        '''
        SELECT id
        FROM users
        WHERE LOWER(username) IN (
            'lneyra',
            'ssanchez',
            'rvasquez',
            'ycoronado'
        )
        '''
    ).fetchall()

    tracking = t['tracking']

    c.close()

    for destinatario in destinatarios_final:

        notify(
            destinatario['id'],
            tid,
            'Requerimiento atendido',
            f"El requerimiento {tracking} fue recibido en obra y quedó completado.",
            'informativa'
        )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/profile')
@login_required
def profile():
    u = user()
    return render_template('profile.html', user=u)


@app.route('/users')
@login_required
def users():
    u = user()
    if u['role'] != 'Sistemas':
        abort(403)
    c = db()
    us = c.execute('select * from users order by full_name').fetchall()
    c.close()
    return render_template('users.html', user=u, users=us)


@app.route('/users/<int:uid>/edit', methods=['POST'])
@login_required
def edit_user(uid):
    u = user()

    if u['role'] != 'Sistemas':
        abort(403)

    full_name = request.form['full_name'].strip()
    username = request.form['username'].strip()
    email = request.form['email'].strip()
    phone = request.form['phone'].strip()
    role = request.form['role'].strip()

    c = db()

    existente = c.execute(
        '''
        SELECT id
        FROM users
        WHERE username = ? COLLATE NOCASE
        AND id != ?
        ''',
        (username, uid)
    ).fetchone()

    if existente:
        c.close()
        flash('Ese nombre de usuario ya está en uso.', 'danger')
        return redirect(url_for('users'))

    c.execute(
        '''
        UPDATE users
        SET full_name=?,
            username=?,
            email=?,
            phone=?,
            role=?
        WHERE id=?
        ''',
        (
            full_name,
            username,
            email,
            phone,
            role,
            uid
        )
    )

    c.commit()
    c.close()

    flash('Usuario actualizado correctamente.', 'success')
    return redirect(url_for('users'))


@app.route('/users/<int:uid>/status', methods=['POST'])
@login_required
def toggle_user_status(uid):
    u = user()

    if u['role'] != 'Sistemas':
        abort(403)

    c = db()

    usuario_objetivo = c.execute(
        'SELECT * FROM users WHERE id=?',
        (uid,)
    ).fetchone()

    if not usuario_objetivo:
        c.close()
        abort(404)

    new_request_estado = 0 if usuario_objetivo['active'] else 1

    c.execute(
        'UPDATE users SET active=? WHERE id=?',
        (new_request_estado, uid)
    )

    c.commit()
    c.close()

    if new_request_estado:
        flash('Usuario activado correctamente.', 'success')
    else:
        flash('Usuario desactivado correctamente.', 'success')

    return redirect(url_for('users'))


@app.route('/users/<int:uid>/password', methods=['POST'])
@login_required
def reset_user_password(uid):
    u = user()

    if u['role'] != 'Sistemas':
        abort(403)

    nueva = request.form['nueva_password'].strip()
    confirmar = request.form['confirmar_password'].strip()

    if len(nueva) < 6:
        flash('La contraseña debe tener al menos 6 caracteres.', 'danger')
        return redirect(url_for('users'))

    if nueva != confirmar:
        flash('Las contraseñas no coinciden.', 'danger')
        return redirect(url_for('users'))

    c = db()

    usuario_objetivo = c.execute(
        'SELECT id FROM users WHERE id=?',
        (uid,)
    ).fetchone()

    if not usuario_objetivo:
        c.close()
        abort(404)

    c.execute(
        '''
        UPDATE users
        SET password_hash=?
        WHERE id=?
        ''',
        (
            generate_password_hash(nueva),
            uid
        )
    )

    c.commit()
    c.close()

    flash('Contraseña asignada correctamente.', 'success')
    return redirect(url_for('users'))


@app.route('/logistics/<int:tid>/receive', methods=['POST'])
@login_required
def logistics_receive(tid):
    u = user()

    if u['username'].lower() not in ('rvasquez', 'ogonzales'):
        abort(403)

    c = db()

    t = c.execute(
        'SELECT * FROM tramites WHERE id=?',
        (tid,)
    ).fetchone()

    if not t or t['tipo'] != 'REQ' or t['estado'] != 'Aprobado':
        c.close()
        abort(400)

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute(
        '''
        INSERT OR IGNORE INTO gestion_logistica(
            tramite_id,
            recibido_fecha
        )
        VALUES(?,?)
        ''',
        (tid, now)
    )

    c.execute(
        '''
        UPDATE gestion_logistica
        SET recibido_fecha=?
        WHERE tramite_id=?
        ''',
        (now, tid)
    )

    c.execute(
        '''
        UPDATE tramites
        SET estado='Recibido por Logística'
        WHERE id=?
        ''',
        (tid,)
    )

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Requerimiento recibido por Logística',
            now
        )
    )

    c.commit()
    c.close()

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/regularizations/<int:rid>', methods=['POST'])
@login_required
def regularize(rid):

    u = user()
    c = db()

    regularizacion = c.execute(
        '''
        SELECT
            r.*,
            t.tracking,
            t.tipo tramite_tipo
        FROM regularizaciones r

        JOIN tramites t
            ON t.id = r.tramite_id

        WHERE r.id=?
        ''',
        (rid,)
    ).fetchone()

    if not regularizacion:
        c.close()
        abort(404)

    # ==========================================
    # PERMISOS
    # ==========================================

    if u['id'] != regularizacion['responsable_id']:
        c.close()
        abort(403)

    # No permitir regularizar dos veces
    if regularizacion['estado'] != 'Pendiente':

        c.close()

        flash(
            'Esta regularización ya fue atendida.',
            'warning'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=regularizacion['tramite_id']
            )
        )

    archivo = request.files.get(
        'archivo_regularizacion'
    )

    # ==========================================
    # ARCHIVO OBLIGATORIO
    # ==========================================

    if not archivo or not archivo.filename:

        c.close()

        flash(
            'Debes adjuntar el documento de regularización.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=regularizacion['tramite_id']
            )
        )

    permitidos = (
        '.pdf',
        '.png',
        '.jpg',
        '.jpeg',
        '.webp',
        '.xml'
    )

    if not archivo.filename.lower().endswith(
        permitidos
    ):

        c.close()

        flash(
            'El archivo debe ser PDF, imagen o XML.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=regularizacion['tramite_id']
            )
        )

    tid = regularizacion['tramite_id']
    tipo_regularizacion = regularizacion['tipo']

    # ==========================================
    # GUARDAR ARCHIVO
    # ==========================================

    save_logistics_files(
        c,
        tid,
        [archivo],
        f"regularizacion_{rid}"
    )

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    # ==========================================
    # MARCAR REGULARIZACIÓN COMO ATENDIDA
    # ==========================================

    c.execute(
        '''
        UPDATE regularizaciones
        SET
            estado='Regularizado',
            fecha_regularizacion=?
        WHERE id=?
        ''',
        (
            now,
            rid
        )
    )

    # ==========================================
    # ACTUALIZAR GESTIÓN LOGÍSTICA
    # ==========================================

    if tipo_regularizacion in (
        'Regularización de compra',
        'Comprobante de compra'
    ):

        c.execute(
            '''
            UPDATE gestion_logistica
            SET comprobante_pendiente=0
            WHERE tramite_id=?
            ''',
            (tid,)
        )

    elif tipo_regularizacion == 'Guía de remisión':

        c.execute(
            '''
            UPDATE gestion_logistica
            SET guia_pendiente=0
            WHERE tramite_id=?
            ''',
            (tid,)
        )

    # ==========================================
    # HISTORIAL
    # ==========================================

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Regularización completada: '
            + tipo_regularizacion,
            now
        )
    )

    c.commit()
    c.close()

    flash(
        'Documento regularizado correctamente.',
        'success'
    )

    return redirect(
        url_for(
            'request_detail',
            tid=tid
        )
    )


@app.route('/logistics/<int:tid>/purchase', methods=['POST'])
@login_required
def logistics_purchase(tid):

    u = user()

    # Solo Logística realiza esta acción
    if u['username'].lower() != 'rvasquez':
        abort(403)

    c = db()

    # ==========================================
    # DATOS DE LA COMPRA INICIAL
    # ==========================================

    fecha_compra = request.form.get(
        'fecha_compra',
        ''
    ).strip()

    tipo_comprobante = request.form.get(
        'tipo_comprobante',
        ''
    ).strip()

    nro_comprobante = request.form.get(
        'nro_comprobante',
        ''
    ).strip()

    monto = parse_decimal(
        request.form.get('monto')
    )

    pendiente_regularizacion = (
        request.form.get('pendiente_regularizacion') == '1'
    )

    archivos_comprobante = [
        archivo
        for archivo in request.files.getlist('comprobante')
        if archivo and archivo.filename
    ]

    # ==========================================
    # VALIDACIONES
    # ==========================================

    if not fecha_compra:

        c.close()

        flash(
            'Debes ingresar la fecha de compra.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    if tipo_comprobante not in (
        'Factura',
        'Boleta',
        'Otro'
    ):

        c.close()

        flash(
            'Selecciona un tipo de comprobante válido.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    if monto <= 0:

        c.close()

        flash(
            'El monto de la compra no es válido.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # ==========================================
    # CREAR GESTIÓN LOGÍSTICA SI NO EXISTE
    # ==========================================

    c.execute(
        '''
        INSERT OR IGNORE INTO gestion_logistica(
            tramite_id
        )
        VALUES(?)
        ''',
        (tid,)
    )

    # ==========================================
    # GUARDAR COMPRA INICIAL
    # ==========================================

    c.execute(
        '''
        UPDATE gestion_logistica
        SET
            fecha_compra=?,
            tipo_comprobante=?,
            nro_comprobante=?,
            monto=?,
            comprobante_pendiente=?,
            estado_pago=NULL,
            forma_pago=NULL
        WHERE tramite_id=?
        ''',
        (
            fecha_compra,
            tipo_comprobante,
            nro_comprobante,
            monto,
            1 if pendiente_regularizacion else 0,
            tid
        )
    )

    # ==========================================
    # GUARDAR COMPROBANTE SI EXISTE
    # ==========================================

    save_logistics_files(
        c,
        tid,
        archivos_comprobante,
        'comprobante'
    )

    # ==========================================
    # CREAR REGULARIZACIÓN
    # ==========================================

    if pendiente_regularizacion:

        create_regularization(
            c,
            tid,
            u['id'],
            'Regularización de compra',
            (
                'Completar la información y documentación definitiva '
                'de la compra.'
            )
        )

    # ==========================================
    # ACTUALIZAR ESTADO DEL REQUERIMIENTO
    # ==========================================

    c.execute(
        '''
        UPDATE tramites
        SET estado='En gestión de compra'
        WHERE id=?
        ''',
        (tid,)
    )

    # ==========================================
    # HISTORIAL
    # ==========================================

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    accion = 'Compra registrada por Logística'

    if pendiente_regularizacion:
        accion += ' - Pendiente de regularización'

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            accion,
            now
        )
    )

    c.commit()
    c.close()

    flash(
        'Compra registrada correctamente.',
        'success'
    )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/logistics/<int:tid>/payment', methods=['POST'])
@login_required
def logistics_payment(tid):

    u = user()

    if u['username'].lower() not in ('rvasquez', 'ogonzales'):
        abort(403)

    forma = request.form.get('forma_pago', '').strip()

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        ''',
        (tid,)
    ).fetchone()

    gestion = c.execute(
        '''
        SELECT *
        FROM gestion_logistica
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    if not gestion:
        c.close()
        abort(400)

    monto = float(gestion['monto'] or 0)

    # Si el monto es S/ 1,900 o más,
    # obligatoriamente pasa a Tesorería.
    if monto >= 1900:
        forma = 'Tesorería'

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # ==========================================
    # 1. TESORERÍA
    # ==========================================

    notificar_tesoreria = False
    tipo_solicitud_tesoreria = None

    if forma == 'Tesorería':

        c.execute(
            '''
            UPDATE gestion_logistica
            SET
                forma_pago='Tesorería',
                estado_pago='Pendiente de Tesorería',
                requiere_reembolso=0
            WHERE tramite_id=?
            ''',
            (tid,)
        )

        existe = c.execute(
            '''
            SELECT id
            FROM solicitudes_tesoreria
            WHERE tramite_id=?
            AND origen='REQ-Compra'
            AND estado='Pendiente'
            ''',
            (tid,)
        ).fetchone()

        if not existe:

            c.execute(
                '''
                INSERT INTO solicitudes_tesoreria(
                    tramite_id,
                    origen,
                    solicitado_por,
                    motivo,
                    monto,
                    estado,
                    fecha_solicitud
                )
                VALUES(?,?,?,?,?,?,?)
                ''',
                (
                    tid,
                    'REQ-Compra',
                    u['id'],
                    'Compra de requerimiento',
                    monto,
                    'Pendiente',
                    now
                )
            )

        notificar_tesoreria = True
        tipo_solicitud_tesoreria = 'pago'

    # ==========================================
    # 2. CAJA DE LOGÍSTICA
    # ==========================================

    elif forma == 'Caja Logística':

        c.execute(
            '''
            UPDATE gestion_logistica
            SET
                forma_pago='Caja Logística',
                estado_pago='Pagado por Logística',
                requiere_reembolso=0
            WHERE tramite_id=?
            ''',
            (tid,)
        )

        save_logistics_files(
            c,
            tid,
            request.files.getlist('voucher'),
            'pago'
        )

    # ==========================================
    # 3. PAGO PERSONAL / REEMBOLSO
    # ==========================================

    elif forma in ('Reembolso', 'Pago personal'):

        c.execute(
            '''
            UPDATE gestion_logistica
            SET
                forma_pago='Pago personal',
                estado_pago='Pagado por Logística',
                requiere_reembolso=1
            WHERE tramite_id=?
            ''',
            (tid,)
        )

        save_logistics_files(
            c,
            tid,
            request.files.getlist('voucher'),
            'pago'
        )

        # Crear solicitud de reembolso para Tesorería
        existe = c.execute(
            '''
            SELECT id
            FROM solicitudes_tesoreria
            WHERE tramite_id=?
            AND origen='REQ-Reembolso'
            AND estado='Pendiente'
            ''',
            (tid,)
        ).fetchone()

        if not existe:

            c.execute(
                '''
                INSERT INTO solicitudes_tesoreria(
                    tramite_id,
                    origen,
                    solicitado_por,
                    motivo,
                    monto,
                    estado,
                    fecha_solicitud
                )
                VALUES(?,?,?,?,?,?,?)
                ''',
                (
                    tid,
                    'REQ-Reembolso',
                    u['id'],
                    'Reembolso de compra',
                    monto,
                    'Pendiente',
                    now
                )
            )

        forma = 'Pago personal'

        notificar_tesoreria = True
        tipo_solicitud_tesoreria = 'reembolso'

    # ==========================================
    # FORMA NO VÁLIDA
    # ==========================================

    else:

        c.close()

        flash(
            'Selecciona una forma de pago.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # ==========================================
    # HISTORIAL
    # ==========================================

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Gestión de pago: ' + forma,
            now
        )
    )

    c.commit()

    yoana_id = None

    if notificar_tesoreria:

        yoana = c.execute(
            '''
            SELECT id
            FROM users
            WHERE LOWER(username)='ycoronado'
            '''
        ).fetchone()

        if yoana:
            yoana_id = yoana['id']

    c.close()

    if yoana_id:

        if tipo_solicitud_tesoreria == 'reembolso':

            notify(
                yoana_id,
                tid,
                'Reembolso pendiente',
                f"El requerimiento {t['tracking']} tiene un reembolso pendiente por S/ {monto:,.2f}.",
                'accion'
            )

        else:

            notify(
                yoana_id,
                tid,
                'Pago requerido',
                f"El requerimiento {t['tracking']} requiere un pago de S/ {monto:,.2f}.",
                'accion'
            )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/treasury/<int:sid>/pay', methods=['POST'])
@login_required
def treasury_pay(sid):

    u = user()

    # Solo Tesorería
    if u['username'].lower() != 'ycoronado':
        abort(403)

    c = db()

    # Obtener solicitud + datos del trámite
    solicitud = c.execute(
        '''
        SELECT
            s.*,
            t.tracking,
            t.tipo
        FROM solicitudes_tesoreria s

        JOIN tramites t
            ON t.id = s.tramite_id

        WHERE s.id=?
        ''',
        (sid,)
    ).fetchone()

    if not solicitud:
        c.close()
        abort(404)

    # No permitir pagar dos veces
    if solicitud['estado'] != 'Pendiente':
        c.close()

        flash(
            'Esta solicitud ya fue atendida.',
            'warning'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=solicitud['tramite_id']
            )
        )

    medio_pago = request.form.get(
        'medio_pago',
        ''
    ).strip()

    banco = request.form.get(
        'banco',
        ''
    ).strip()

    fecha_pago = request.form.get(
        'fecha_pago',
        ''
    ).strip()

    monto = parse_decimal(
        request.form.get('monto')
    )

    nro_operacion = request.form.get(
        'nro_operacion',
        ''
    ).strip()

    comprobante = request.files.get(
        'comprobante_pago'
    )

    # ==========================================
    # VALIDACIONES
    # ==========================================

    if not medio_pago:

        c.close()

        flash(
            'Selecciona el medio de pago.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=solicitud['tramite_id']
            )
        )

    if not fecha_pago:

        c.close()

        flash(
            'Ingresa la fecha del pago.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=solicitud['tramite_id']
            )
        )

    if monto <= 0:

        c.close()

        flash(
            'El monto del pago no es válido.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=solicitud['tramite_id']
            )
        )

    # El comprobante de pago sí es obligatorio
    if not comprobante or not comprobante.filename:

        c.close()

        flash(
            'Debes adjuntar el comprobante del pago.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=solicitud['tramite_id']
            )
        )

    permitidos = (
        '.pdf',
        '.png',
        '.jpg',
        '.jpeg',
        '.webp'
    )

    if not comprobante.filename.lower().endswith(
        permitidos
    ):

        c.close()

        flash(
            'El comprobante debe ser PDF o imagen.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=solicitud['tramite_id']
            )
        )

    # ==========================================
    # GUARDAR COMPROBANTE
    # ==========================================

    nombre_original = comprobante.filename

    nombre_archivo = secure_filename(
        f"TES_{sid}_{int(datetime.now().timestamp())}_{comprobante.filename}"
    )

    comprobante.save(
        os.path.join(
            UPLOADS,
            nombre_archivo
        )
    )

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    # ==========================================
    # REGISTRAR PAGO
    # ==========================================

    c.execute(
        '''
        INSERT INTO pagos_tesoreria(
            solicitud_id,
            medio_pago,
            banco,
            monto,
            nro_operacion,
            nombre_original,
            nombre_archivo,
            fecha
        )
        VALUES(?,?,?,?,?,?,?,?)
        ''',
        (
            sid,
            medio_pago,
            banco,
            monto,
            nro_operacion,
            nombre_original,
            nombre_archivo,
            fecha_pago
        )
    )

    # Marcar solicitud atendida
    c.execute(
        '''
        UPDATE solicitudes_tesoreria
        SET
            estado='Atendido',
            fecha_atencion=?
        WHERE id=?
        ''',
        (
            now,
            sid
        )
    )

    tid = solicitud['tramite_id']

    # ==========================================
    # SI ES PAGO DE COMPRA DEL REQ
    # ==========================================

    if solicitud['origen'] == 'REQ-Compra':

        c.execute(
            '''
            UPDATE gestion_logistica
            SET estado_pago='Pagado por Tesorería'
            WHERE tramite_id=?
            ''',
            (tid,)
        )

        accion_historial = (
            'Pago registrado por Tesorería'
        )

    # ==========================================
    # SI ES REEMBOLSO
    # ==========================================

    elif solicitud['origen'] == 'REQ-Reembolso':

        c.execute(
            '''
            UPDATE gestion_logistica
            SET requiere_reembolso=0
            WHERE tramite_id=?
            ''',
            (tid,)
        )

        accion_historial = (
            'Reembolso atendido por Tesorería'
        )

    else:

        accion_historial = (
            'Pago registrado por Tesorería'
        )

    # ==========================================
    # HISTORIAL
    # ==========================================

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            accion_historial,
            now
        )
    )

    c.commit()

    # Buscar a Rodrigo
    rodrigo = c.execute(
        '''
        SELECT id
        FROM users
        WHERE LOWER(username)='rvasquez'
        '''
    ).fetchone()

    rodrigo_id = (
        rodrigo['id']
        if rodrigo
        else None
    )

    origen = solicitud['origen']
    tracking = solicitud['tracking']

    c.close()

    # ==========================================
    # NOTIFICAR A RODRIGO
    # ==========================================

    if rodrigo_id:

        if origen == 'REQ-Compra':

            notify(
                rodrigo_id,
                tid,
                'Pago realizado por Tesorería',
                f"El requerimiento {tracking} ya fue pagado por Tesorería y puede continuar con el despacho a obra.",
                'accion'
            )

        elif origen == 'REQ-Reembolso':

            notify(
                rodrigo_id,
                tid,
                'Reembolso realizado',
                f"El reembolso correspondiente al requerimiento {tracking} fue atendido por Tesorería.",
                'informativa'
            )

    flash(
        'Pago registrado correctamente.',
        'success'
    )

    return redirect(
        url_for(
            'request_detail',
            tid=tid
        )
    )


@app.route('/logistics/<int:tid>/dispatch', methods=['POST'])
@login_required
def logistics_dispatch(tid):
    u = user()

    if u['username'].lower() not in ('rvasquez', 'ogonzales'):
        abort(403)

    guia_numero = request.form.get('guia_numero', '').strip()
    guia_fecha = request.form.get('guia_fecha', '')

    guia_pendiente = (
        1 if request.form.get('guia_pendiente') else 0
    )

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        ''',
        (tid,)
    ).fetchone()

    # ==========================================
    # VALIDAR QUE LA COMPRA YA ESTÉ PAGADA
    # ==========================================

    gestion = c.execute(
        '''
        SELECT *
        FROM gestion_logistica
        WHERE tramite_id=?
        ''',
        (tid,)
    ).fetchone()

    if not gestion:
        c.close()
        abort(400)

    if gestion['estado_pago'] not in (
        'Pagado por Logística',
        'Pagado por Tesorería'
    ):
        c.close()

        flash(
            'No puedes enviar a obra mientras el pago esté pendiente.',
            'danger'
        )

        return redirect(
            url_for('request_detail', tid=tid)
        )

    # ==========================================
    # GUARDAR GUÍA
    # ==========================================

    save_logistics_files(
        c,
        tid,
        request.files.getlist('guia'),
        'guia'
    )

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute(
        '''
        UPDATE gestion_logistica
        SET
            guia_numero=?,
            guia_fecha=?,
            guia_pendiente=?,
            enviado_fecha=?
        WHERE tramite_id=?
        ''',
        (
            guia_numero,
            guia_fecha,
            guia_pendiente,
            now,
            tid
        )
    )

    # ==========================================
    # REGULARIZACIÓN DE GUÍA
    # ==========================================

    if guia_pendiente:

        create_regularization(
            c,
            tid,
            u['id'],
            'Guía de remisión',
            'Regularizar la guía de remisión correspondiente al envío a obra.'
        )

    c.execute(
        '''
        UPDATE tramites
        SET estado='Enviado a obra'
        WHERE id=?
        ''',
        (tid,)
    )

    c.execute(
        '''
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        ''',
        (
            tid,
            u['id'],
            'Material enviado a obra',
            now
        )
    )

    c.commit()

    # ==========================================
    # NOTIFICAR ENVÍO A OBRA
    # ==========================================

    destinatarios_obra = c.execute(
        '''
        SELECT id
        FROM users
        WHERE LOWER(username) IN (
            'amoreno',
            'jbecerra'
        )
        '''
    ).fetchall()

    c.close()

    for destinatario in destinatarios_obra:

        notify(
            destinatario['id'],
            tid,
            'Requerimiento enviado a obra',
            f"El requerimiento {t['tracking']} fue enviado a obra.",
            'informativa'
        )

    return redirect(
        url_for('request_detail', tid=tid)
    )


if __name__ == '__main__':
    init_db()
    app.run(debug=True, host='127.0.0.1', port=5000)
