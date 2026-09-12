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
import uuid
from urllib.parse import quote

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
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase.pdfmetrics import stringWidth


BASE = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(BASE, 'lanr.db')

UPLOADS = os.path.join(BASE, 'uploads')
os.makedirs(UPLOADS, exist_ok=True)

app = Flask(__name__)

# Desarrollo local: recargar cambios automáticamente y no cachear CSS/JS.
app.config['TEMPLATES_AUTO_RELOAD'] = True
app.config['SEND_FILE_MAX_AGE_DEFAULT'] = 0


@app.after_request
def add_no_cache_headers(response):
    if request.path.startswith('/static/'):
        response.headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0'
        response.headers['Pragma'] = 'no-cache'
        response.headers['Expires'] = '0'
    return response


app.secret_key = os.environ.get(
    'LANR_SECRET_KEY',
    'lanr-local-v1-dev'
)

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
    ('Luis Neyra', 'lNeyra', 'Gerencia General'), ('Sara Sánchez',
                                                   'sSanchez', 'Administración'),
    ('Rodrigo Vásquez', 'rVasquez',
     'Logística'), ('Yoana Coronado', 'yCoronado', 'Tesorería'),
    ('Olenka Gonzales', 'oGonzales', 'Sistemas'),
    ('Yenny Condorachay', 'yCondorachay', 'Contabilidad')]


def db():
    c = sqlite3.connect(DB)
    c.row_factory = sqlite3.Row
    return c


def init_db():

    c = db()

    c.executescript('''

    CREATE TABLE IF NOT EXISTS obras(
        id INTEGER PRIMARY KEY,
        nombre TEXT NOT NULL,
        codigo TEXT UNIQUE NOT NULL,
        proyecto TEXT,
        ubicacion TEXT,
        active INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS usuario_obra(
        id INTEGER PRIMARY KEY,
        usuario_id INTEGER NOT NULL,
        obra_id INTEGER NOT NULL,
        active INTEGER DEFAULT 1,

        UNIQUE(usuario_id, obra_id),

        FOREIGN KEY(usuario_id)
            REFERENCES users(id),

        FOREIGN KEY(obra_id)
            REFERENCES obras(id)
    );


    CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY,
        full_name TEXT,
        username TEXT UNIQUE COLLATE NOCASE,
        password_hash TEXT,
        role TEXT,
        email TEXT,
        phone TEXT,
        profile_photo TEXT,
        must_change INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT,
        last_login TEXT
    );

    CREATE TABLE IF NOT EXISTS unidades_catalogo(
        id INTEGER PRIMARY KEY,
        abreviatura TEXT NOT NULL COLLATE NOCASE,
        uso TEXT NOT NULL,
        active INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT,
        UNIQUE(abreviatura, uso)
    );

    CREATE TABLE IF NOT EXISTS tramites(
        id INTEGER PRIMARY KEY,
        obra_id INTEGER,
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

        prioridad TEXT DEFAULT 'Normal',
        fecha_requerida TEXT,

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
        prioridad TEXT DEFAULT 'Normal',
        fecha_requerida TEXT,
        item_key TEXT,
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

    CREATE TABLE IF NOT EXISTS compras_logistica(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        fecha_compra TEXT NOT NULL,
        tipo_comprobante TEXT NOT NULL,
        nro_comprobante TEXT,
        monto REAL DEFAULT 0,
        nombre_original TEXT,
        nombre_archivo TEXT,
        creado TEXT
    );

    CREATE TABLE IF NOT EXISTS compra_detalle(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        item_id INTEGER NOT NULL,
        compra_id INTEGER,
        cantidad_comprada REAL DEFAULT 0,
        observacion TEXT,
        creado TEXT
    );

    CREATE TABLE IF NOT EXISTS regularizacion_compra_detalle(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        descripcion TEXT NOT NULL,
        cantidad REAL NOT NULL DEFAULT 0,
        precio_unitario REAL NOT NULL DEFAULT 0,
        precio_total REAL NOT NULL DEFAULT 0,
        creado TEXT
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


    CREATE TABLE IF NOT EXISTS proveedores(
        id INTEGER PRIMARY KEY,
        nombre TEXT NOT NULL,
        documento TEXT,
        active INTEGER DEFAULT 1,
        creado TEXT,
        actualizado TEXT
    );

    CREATE TABLE IF NOT EXISTS cotizaciones(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        proveedor_id INTEGER NOT NULL,
        tipo_sustento TEXT NOT NULL DEFAULT 'Cotización',
        fecha TEXT,
        estado TEXT NOT NULL DEFAULT 'Borrador',
        observacion TEXT,
        creado_por INTEGER,
        creado TEXT,
        enviado_fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS cotizacion_items(
        id INTEGER PRIMARY KEY,
        cotizacion_id INTEGER NOT NULL,
        item_id INTEGER NOT NULL,
        cantidad REAL NOT NULL DEFAULT 0,
        precio_unitario REAL NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS cotizacion_archivos(
        id INTEGER PRIMARY KEY,
        cotizacion_id INTEGER NOT NULL,
        nombre_original TEXT,
        nombre_archivo TEXT,
        creado TEXT
    );

    CREATE TABLE IF NOT EXISTS autorizaciones_compra(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        autorizado_por INTEGER NOT NULL,
        estado TEXT NOT NULL DEFAULT 'Autorizada',
        motivo_anulacion TEXT,
        fecha TEXT,
        anulada_fecha TEXT
    );

    CREATE TABLE IF NOT EXISTS autorizacion_items(
        id INTEGER PRIMARY KEY,
        autorizacion_id INTEGER NOT NULL,
        cotizacion_id INTEGER NOT NULL,
        item_id INTEGER NOT NULL,
        proveedor_id INTEGER NOT NULL,
        cantidad REAL NOT NULL DEFAULT 0,
        precio_unitario REAL NOT NULL DEFAULT 0,
        subtotal REAL NOT NULL DEFAULT 0
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

    CREATE TABLE IF NOT EXISTS archivos_pago_sp(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        nombre_original TEXT NOT NULL,
        nombre_archivo TEXT NOT NULL,
        fecha TEXT NOT NULL
    );

    ''')

    # =========================================================
    # CATÁLOGO INICIAL DE UNIDADES
    # =========================================================
    now_units = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    unidades_iniciales = [
        ('CAJA', 'REQ'), ('UND', 'AMBOS'), ('PAQ', 'REQ'),
        ('DOC', 'REQ'), ('BLS', 'REQ'), ('BALDE', 'REQ'),
        ('VARILLAS', 'REQ'), ('GLN', 'REQ'), ('ROLLO', 'REQ'),
        ('PLAN', 'REQ'), ('M', 'REQ'), ('HOJAS', 'REQ'),
        ('JUEGO', 'REQ'), ('GLB', 'REQ'), ('KG',
                                           'AMBOS'), ('GLB', 'SP'), ('DIA', 'SP'),
        ('HE', 'SP'), ('GAL', 'SP'), ('HM', 'SP'),
        ('PTO', 'SP'), ('MES', 'SP'), ('M3', 'SP')
    ]

    for abreviatura, uso in unidades_iniciales:
        c.execute(
            '''
            INSERT OR IGNORE INTO unidades_catalogo(
                abreviatura, uso, active, created_at, updated_at
            ) VALUES(?,?,1,?,?)
            ''',
            (abreviatura, uso, now_units, now_units)
        )

    # =========================================================
    # MIGRACIONES DE BASE DE DATOS
    # =========================================================

    columnas_tramites = [
        row['name']
        for row in c.execute(
            "PRAGMA table_info(tramites)"
        ).fetchall()
    ]

    if 'obra_id' not in columnas_tramites:
        c.execute(
            '''
            ALTER TABLE tramites
            ADD COLUMN obra_id INTEGER
            '''
        )

    if 'prioridad' not in columnas_tramites:
        c.execute(
            "ALTER TABLE tramites ADD COLUMN prioridad TEXT DEFAULT 'Normal'"
        )

    if 'fecha_requerida' not in columnas_tramites:
        c.execute(
            "ALTER TABLE tramites ADD COLUMN fecha_requerida TEXT"
        )

    columnas_items = [
        row['name']
        for row in c.execute(
            "PRAGMA table_info(items)"
        ).fetchall()
    ]

    if 'prioridad' not in columnas_items:
        c.execute("ALTER TABLE items ADD COLUMN prioridad TEXT DEFAULT 'Normal'")
    if 'fecha_requerida' not in columnas_items:
        c.execute("ALTER TABLE items ADD COLUMN fecha_requerida TEXT")
    if 'item_key' not in columnas_items:
        c.execute("ALTER TABLE items ADD COLUMN item_key TEXT")

    # Nombre oficial definido para esta clasificación de requerimiento.
    c.execute(
        "UPDATE items SET seccion='Seguridad en obra' "
        "WHERE seccion='Ing. de seguridad'"
    )

    c.execute(
        '''
        CREATE TABLE IF NOT EXISTS item_imagenes(
            id INTEGER PRIMARY KEY,
            tramite_id INTEGER NOT NULL,
            item_key TEXT NOT NULL,
            nombre_original TEXT,
            nombre_archivo TEXT NOT NULL,
            creado TEXT
        )
        '''
    )

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

    # Datos de la gestión/cotización previa al pago.
    for columna, definicion in (
        ('monto_cotizado', 'REAL DEFAULT 0'),
        ('proveedor_cotizado', 'TEXT'),
        ('ruc_proveedor', 'TEXT'),
        ('fecha_cotizacion', 'TEXT'),
    ):
        if columna not in columnas_gestion:
            c.execute(
                f'ALTER TABLE gestion_logistica ADD COLUMN {columna} {definicion}')

    if 'medio_envio' not in columnas_gestion:
        c.execute(
            '''
            ALTER TABLE gestion_logistica
            ADD COLUMN medio_envio TEXT
            '''
        )

    if 'responsable_transporte' not in columnas_gestion:
        c.execute(
            '''
            ALTER TABLE gestion_logistica
            ADD COLUMN responsable_transporte TEXT
            '''
        )

    if 'costo_envio' not in columnas_gestion:
        c.execute(
            '''
            ALTER TABLE gestion_logistica
            ADD COLUMN costo_envio REAL DEFAULT 0
            '''
        )

    if 'observacion_envio' not in columnas_gestion:
        c.execute(
            '''
            ALTER TABLE gestion_logistica
            ADD COLUMN observacion_envio TEXT
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

    columnas_tesoreria = [row['name'] for row in c.execute(
        "PRAGMA table_info(solicitudes_tesoreria)").fetchall()]
    for columna, definicion in (
        ('autorizacion_id', 'INTEGER'),
        ('proveedor_id', 'INTEGER'),
        ('proveedor_nombre', 'TEXT'),
    ):
        if columna not in columnas_tesoreria:
            c.execute(
                f'ALTER TABLE solicitudes_tesoreria ADD COLUMN {columna} {definicion}')

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

    # =========================================================
    # OBRA INICIAL - LA VEGA
    # =========================================================

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    c.execute(
        '''
        INSERT OR IGNORE INTO obras(
            nombre,
            codigo,
            proyecto,
            ubicacion,
            active,
            created_at,
            updated_at
        )
        VALUES(?,?,?,?,1,?,?)
        ''',
        (
            'La Vega',
            'LAVEGA',
            PROJECT,
            PLACE,
            now,
            now
        )
    )

    for n, u, r in USERS:

        if not c.execute(
            '''
            SELECT 1
            FROM users
            WHERE username=?
            COLLATE NOCASE
            ''',
            (u,)
        ).fetchone():

            now = datetime.now().strftime(
                '%Y-%m-%d %H:%M:%S'
            )

            c.execute(
                '''
                INSERT INTO users(
                    full_name,
                    username,
                    password_hash,
                    role,
                    created_at,
                    updated_at
                )
                VALUES(?,?,?,?,?,?)
                ''',
                (
                    n,
                    u,
                    generate_password_hash('Lanr@2026'),
                    r,
                    now,
                    now
                )
            )

    # =========================================================
    # ASIGNAR USUARIOS INICIALES A LA VEGA
    # =========================================================

    obra_la_vega = c.execute(
        '''
        SELECT id
        FROM obras
        WHERE codigo='LAVEGA'
        '''
    ).fetchone()

    if obra_la_vega:

        # Los trámites existentes pertenecen a La Vega.
        c.execute(
            '''
            UPDATE tramites
            SET obra_id=?
            WHERE obra_id IS NULL
            ''',
            (obra_la_vega['id'],)
        )

        usuarios_iniciales = c.execute(
            '''
            SELECT id
            FROM users
            '''
        ).fetchall()

        for usuario_inicial in usuarios_iniciales:

            c.execute(
                '''
                INSERT OR IGNORE INTO usuario_obra(
                    usuario_id,
                    obra_id,
                    active
                )
                VALUES(?,?,1)
                ''',
                (
                    usuario_inicial['id'],
                    obra_la_vega['id']
                )
            )

    c.commit()
    c.close()


def active_project():
    """
    Devuelve la obra activa del usuario.
    Si todavía no existe una obra seleccionada,
    utiliza la primera obra activa asignada al usuario.
    """

    if 'uid' not in session:
        return None

    c = db()

    obra = None
    obra_id = session.get('obra_id')

    # Intentar utilizar la obra guardada en sesión.
    if obra_id:

        obra = c.execute(
            '''
            SELECT o.*
            FROM obras o

            JOIN usuario_obra uo
                ON uo.obra_id = o.id

            WHERE o.id=?
            AND uo.usuario_id=?
            AND o.active=1
            AND uo.active=1
            ''',
            (
                obra_id,
                session['uid']
            )
        ).fetchone()

    # Si no hay obra válida seleccionada,
    # tomar la primera obra asignada al usuario.
    if not obra:

        obra = c.execute(
            '''
            SELECT o.*
            FROM obras o

            JOIN usuario_obra uo
                ON uo.obra_id = o.id

            WHERE uo.usuario_id=?
            AND o.active=1
            AND uo.active=1

            ORDER BY o.id
            LIMIT 1
            ''',
            (session['uid'],)
        ).fetchone()

        if obra:
            session['obra_id'] = obra['id']

    c.close()

    return obra


@app.context_processor
def project_context():

    if 'uid' not in session:
        return {
            'active_project': None,
            'available_projects': []
        }

    c = db()

    available_projects = c.execute(
        '''
        SELECT o.*
        FROM obras o

        JOIN usuario_obra uo
            ON uo.obra_id = o.id

        WHERE uo.usuario_id=?
        AND o.active=1
        AND uo.active=1

        ORDER BY o.nombre
        ''',
        (session['uid'],)
    ).fetchall()

    c.close()

    return {
        'active_project': active_project(),
        'available_projects': available_projects
    }


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
            return redirect(
                url_for('login')
            )

        c = db()

        account = c.execute(
            '''
            SELECT active
            FROM users
            WHERE id=?
            ''',
            (session['uid'],)
        ).fetchone()

        c.close()

        if not account or not account['active']:

            session.clear()

            flash(
                'Su cuenta está inactiva. Contacte a Sistemas.',
                'danger'
            )

            return redirect(
                url_for('login')
            )

        return fn(*a, **k)

    return w


def next_code(tipo, year, obra_id):

    prefix = 'REQ' if tipo == 'REQ' else 'SP'

    c = db()

    obra = c.execute(
        '''
        SELECT codigo
        FROM obras
        WHERE id=?
        AND active=1
        ''',
        (obra_id,)
    ).fetchone()

    if not obra:
        c.close()
        raise ValueError('La obra seleccionada no existe o está inactiva.')

    codigo_obra = obra['codigo'].upper()

    row = c.execute(
        '''
        SELECT tracking
        FROM tramites
        WHERE obra_id=?
        AND tracking LIKE ?
        ORDER BY id DESC
        LIMIT 1
        ''',
        (
            obra_id,
            f'{prefix}-{codigo_obra}-{year}-%'
        )
    ).fetchone()

    c.close()

    if row:
        number = int(
            row['tracking'].split('-')[-1]
        ) + 1
    else:
        number = 1

    return (
        f'{prefix}-{codigo_obra}-'
        f'{year}-{number:03d}'
    )


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


def validate_password(password):
    """
    Política de contraseñas de LANR.

    Debe contener:
    - Mínimo 8 caracteres
    - Una letra mayúscula
    - Una letra minúscula
    - Un número
    - Un carácter especial
    """

    if len(password) < 8 or len(password) > 64:
        return (
            False,
            'La contraseña debe tener entre 8 y 64 caracteres.'
        )

    if not re.search(r'[A-Z]', password):
        return (
            False,
            'La contraseña debe contener al menos una letra mayúscula.'
        )

    if not re.search(r'[a-z]', password):
        return (
            False,
            'La contraseña debe contener al menos una letra minúscula.'
        )

    if not re.search(r'\d', password):
        return (
            False,
            'La contraseña debe contener al menos un número.'
        )

    if not re.search(r'[^A-Za-z0-9]', password):
        return (
            False,
            'La contraseña debe contener al menos un carácter especial.'
        )

    return True, ''


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

    mensaje['Subject'] = 'Recuperación de contraseña | LANR'
    mensaje['From'] = f'LANR Sistemas <{MAIL_SENDER}>'
    mensaje['To'] = usuario['email']

    # Versión texto por compatibilidad
    mensaje.set_content(
        f"""Hola {usuario['full_name']},

Recibimos una solicitud para restablecer la contraseña de su cuenta en el Sistema Interno de Gestión de LANR INVERSIONES.

Puede crear una nueva contraseña ingresando al siguiente enlace:

{reset_url}

Este enlace estará disponible durante 30 minutos.

Si no solicitó este cambio, puede ignorar este correo.

LANR INVERSIONES E.I.R.L.
Área de Sistemas
"""
    )

    # Versión HTML
    mensaje.add_alternative(
        f"""
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
</head>

<body style="
  margin:0;
  padding:0;
  background:#f4f7f9;
  font-family:Segoe UI, Arial, sans-serif;
  color:#1e2d38;
">

  <table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="background:#f4f7f9; padding:36px 15px;"
  >
    <tr>
      <td align="center">

        <table
          width="100%"
          cellpadding="0"
          cellspacing="0"
          border="0"
          style="
            max-width:560px;
            background:#ffffff;
            border:1px solid #dde5ea;
            border-radius:16px;
            overflow:hidden;
          "
        >

          <!-- CABECERA -->
          <tr>
            <td
              style="
                background:#173b56;
                padding:26px 32px;
              "
            >

              <div
                style="
                  color:#ffffff;
                  font-size:20px;
                  font-weight:700;
                "
              >
                LANR INVERSIONES
              </div>

              <div
                style="
                  color:#b9c8d2;
                  font-size:12px;
                  margin-top:4px;
                "
              >
                Sistema Interno de Gestión
              </div>

            </td>
          </tr>


          <!-- CUERPO -->
          <tr>
            <td style="padding:34px 32px;">

              <div
                style="
                  color:#c91f2a;
                  font-size:11px;
                  font-weight:700;
                  letter-spacing:1px;
                  margin-bottom:10px;
                "
              >
                RECUPERACIÓN DE CUENTA
              </div>

              <h1
                style="
                  margin:0;
                  color:#17354c;
                  font-size:25px;
                  line-height:1.25;
                "
              >
                Restablezca su contraseña
              </h1>

              <div
                style="
                  width:38px;
                  height:3px;
                  background:#c91f2a;
                  border-radius:4px;
                  margin:14px 0 22px;
                "
              ></div>

              <p
                style="
                  margin:0 0 16px;
                  color:#405664;
                  font-size:14px;
                  line-height:1.6;
                "
              >
                Hola <strong>{usuario['full_name']}</strong>,
              </p>

              <p
                style="
                  margin:0 0 24px;
                  color:#405664;
                  font-size:14px;
                  line-height:1.6;
                "
              >
                Recibimos una solicitud para restablecer la contraseña
                de su cuenta en el Sistema Interno de Gestión de LANR.
              </p>


              <!-- BOTÓN -->
              <table
                cellpadding="0"
                cellspacing="0"
                border="0"
                width="100%"
              >
                <tr>
                  <td align="center">

                    <a
                      href="{reset_url}"
                      style="
                        display:inline-block;
                        background:#173b56;
                        color:#ffffff;
                        text-decoration:none;
                        padding:13px 25px;
                        border-radius:9px;
                        font-size:14px;
                        font-weight:700;
                      "
                    >
                      Crear nueva contraseña
                    </a>

                  </td>
                </tr>
              </table>


              <div
                style="
                  margin-top:28px;
                  padding:14px 16px;
                  background:#eef3f6;
                  border-radius:9px;
                  color:#506674;
                  font-size:12px;
                  line-height:1.55;
                "
              >
                Este enlace será válido durante
                <strong>30 minutos</strong>.
              </div>

              <p
                style="
                  margin:22px 0 0;
                  color:#71818c;
                  font-size:12px;
                  line-height:1.55;
                "
              >
                Si no solicitaste este cambio, no necesitas realizar
                ninguna acción.
              </p>

            </td>
          </tr>


          <!-- PIE -->
          <tr>
            <td
              style="
                padding:19px 32px;
                border-top:1px solid #edf1f3;
                text-align:center;
                color:#8a979f;
                font-size:11px;
              "
            >
              LANR INVERSIONES E.I.R.L.<br>
              Área de Sistemas
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
""",
        subtype='html'
    )

    contexto = ssl.create_default_context()

    with smtplib.SMTP(
        'smtp.gmail.com',
        587,
        timeout=20
    ) as servidor:

        servidor.starttls(
            context=contexto
        )

        servidor.login(
            MAIL_SENDER,
            MAIL_APP_PASSWORD
        )

        servidor.send_message(
            mensaje
        )


TRAMITE_CREATOR_ROLES = (
    'Gerencia de Obra',
    'Control y Planeamiento'
)

OFFICE_REQUIREMENT_ROLES = (
    'Administración',
    'Logística',
    'Tesorería',
    'Sistemas'
)

PAYMENT_REQUEST_CREATOR_ROLES = (
    'Gerencia de Obra',
    'Control y Planeamiento',
    'Contabilidad',
    'Administración'
)


def can_create_requirement(usuario):
    return usuario['role'] in TRAMITE_CREATOR_ROLES + OFFICE_REQUIREMENT_ROLES


def can_create_payment_request(usuario):
    return usuario['role'] in PAYMENT_REQUEST_CREATOR_ROLES


def can_create_request(usuario):
    return can_create_requirement(usuario) or can_create_payment_request(usuario)


def _extract_form_sequence(tipo, numero, year):
    numero = (numero or '').strip()
    if tipo == 'REQ':
        m = re.fullmatch(r'(\d+)\s*-\s*(\d{4})', numero)
        if m and m.group(2) == str(year):
            return int(m.group(1))
    elif tipo == 'SP':
        m = re.fullmatch(r'(\d{4})\s*-\s*(\d+)', numero)
        if m and m.group(1) == str(year):
            return int(m.group(2))
    return None


def get_form_number_info(c, obra_id, tipo, year):
    rows = c.execute(
        '''
        SELECT numero
        FROM tramites
        WHERE obra_id=? AND tipo=?
        ''',
        (obra_id, tipo)
    ).fetchall()

    seqs = []
    for row in rows:
        seq = _extract_form_sequence(tipo, row['numero'], year)
        if seq is not None:
            seqs.append(seq)

    last = max(seqs) if seqs else None
    suggested = (last + 1) if last is not None else None
    return {'last': last, 'suggested': suggested, 'year': int(year)}


def compose_form_number(tipo, sequence, year):
    sequence = str(sequence or '').strip()
    if not re.fullmatch(r'\d+', sequence):
        raise ValueError('El número correlativo debe contener solo números.')
    sequence = str(int(sequence))
    if tipo == 'REQ':
        return f'{sequence}-{year}'
    if tipo == 'SP':
        return f'{year}-{sequence}'
    raise ValueError('Tipo de trámite no válido.')


def parse_decimal(valor):
    try:
        return float(
            str(valor or '0')
            .replace(',', '')
            .strip()
        )
    except:
        return 0.0


@app.template_filter('fmt_num')
def fmt_num(valor):
    """Muestra enteros sin .0 y decimales sin ceros innecesarios."""
    try:
        numero = float(valor)
    except (TypeError, ValueError):
        return '' if valor is None else str(valor)
    if numero.is_integer():
        return str(int(numero))
    return f'{numero:.10f}'.rstrip('0').rstrip('.')


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

    item_images = c.execute(
        '''
        SELECT *
        FROM item_imagenes
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

    # El PERIODO USO del formato LANR es fijo para todos los requerimientos.
    # No depende de la sección/tipo de material seleccionado.
    periodo = 'EJECUCIÓN'

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

    w_etiqueta = 22 * mm
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
            4.6,
            'Helvetica-Bold',
            rojo if idx == 0 else negro
        )

        draw_centered_text(
            valor,
            x_datos + w_etiqueta,
            yy + fila_dato / 2,
            w_valor,
            'Helvetica-Bold' if idx in (0, 3) else 'Helvetica',
            5.1
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

    pdf_item_counter = 0
    pdf_item_number_by_key = {}

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

            pdf_item_counter += 1
            nro = f'{pdf_item_counter:02d}'
            if item['item_key']:
                pdf_item_number_by_key[item['item_key']] = pdf_item_counter

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

    # =====================================================
    # IMÁGENES DE REFERENCIA (solo cuando existen)
    # La prioridad y fecha requerida son internas y NO se imprimen.
    # =====================================================
    if item_images:
        item_map = {i['item_key']: i for i in items if i['item_key']}
        grupos = {}
        for img in item_images:
            if img['item_key'] in item_map:
                grupos.setdefault(img['item_key'], []).append(img)

        if grupos:
            # Continúa en la misma página cuando todavía hay espacio.
            # Solo crea una página adicional cuando realmente es necesario.
            y_img = y - 11 * mm
            if y_img < 78 * mm:
                pdf.showPage()
                y_img = A4[1] - 18 * mm

            pdf.setFont('Helvetica-Bold', 10)
            pdf.drawString(18 * mm, y_img, 'IMÁGENES DE REFERENCIA')
            y_img -= 8 * mm

            for key, imgs in grupos.items():
                item = item_map[key]
                if y_img < 70 * mm:
                    pdf.showPage()
                    y_img = A4[1] - 18 * mm
                    pdf.setFont('Helvetica-Bold', 10)
                    pdf.drawString(18 * mm, y_img, 'IMÁGENES DE REFERENCIA')
                    y_img -= 8 * mm

                pdf.setFont('Helvetica-Bold', 7.5)
                nro_img = pdf_item_number_by_key.get(key)
                if nro_img is None:
                    try:
                        nro_img = int(item['nro'])
                    except Exception:
                        nro_img = item['nro'] or ''
                try:
                    titulo_img = f"Ítem {int(nro_img):02d} - {item['descripcion']}"
                except Exception:
                    titulo_img = f"Ítem {nro_img} - {item['descripcion']}"
                pdf.drawString(18 * mm, y_img, titulo_img[:110])
                y_img -= 5 * mm

                x_img = 18 * mm
                fila_altura = 0
                for img in imgs:
                    ruta = os.path.join(UPLOADS, img['nombre_archivo'])
                    if not os.path.exists(ruta):
                        continue
                    try:
                        ir = ImageReader(ruta)
                        iw, ih = ir.getSize()
                        max_w, max_h = 78 * mm, 45 * mm
                        scale = min(max_w / iw, max_h / ih)
                        dw, dh = iw * scale, ih * scale

                        if x_img + dw > A4[0] - 18 * mm:
                            y_img -= fila_altura + 5 * mm
                            x_img = 18 * mm
                            fila_altura = 0

                        if y_img - dh < 18 * mm:
                            pdf.showPage()
                            y_img = A4[1] - 18 * mm
                            pdf.setFont('Helvetica-Bold', 10)
                            pdf.drawString(
                                18 * mm, y_img, 'IMÁGENES DE REFERENCIA')
                            y_img -= 8 * mm
                            pdf.setFont('Helvetica-Bold', 7.5)
                            pdf.drawString(18 * mm, y_img, titulo_img[:110])
                            y_img -= 5 * mm
                            x_img = 18 * mm
                            fila_altura = 0

                        pdf.drawImage(
                            ir, x_img, y_img - dh, width=dw, height=dh,
                            preserveAspectRatio=True, mask='auto'
                        )
                        x_img += 84 * mm
                        fila_altura = max(fila_altura, dh)
                    except Exception:
                        continue

                y_img -= fila_altura + 8 * mm

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
        '''SELECT t.*,u.full_name creador_nombre FROM tramites t JOIN users u ON u.id=t.creador WHERE t.id=?''', (tid,)).fetchone()
    if not t or t['tipo'] != 'SP':
        c.close()
        abort(404)
    items = c.execute(
        'SELECT * FROM items WHERE tramite_id=? ORDER BY nro', (tid,)).fetchall()
    aps = c.execute(
        '''SELECT a.*,u.full_name nombre FROM approvals a JOIN users u ON u.id=a.usuario_id WHERE a.tramite_id=? ORDER BY a.id''', (tid,)).fetchall()
    modalidades = c.execute(
        'SELECT modalidad FROM sp_modalidades WHERE tramite_id=? ORDER BY id', (tid,)).fetchall()
    cuentas = c.execute(
        'SELECT banco,cuenta_cci FROM sp_cuentas WHERE tramite_id=? ORDER BY id', (tid,)).fetchall()
    comps = c.execute(
        'SELECT * FROM sp_comprobantes WHERE tramite_id=? ORDER BY orden,id', (tid,)).fetchall()
    adjuntos = c.execute(
        'SELECT * FROM attachments WHERE tramite_id=? ORDER BY orden,id', (tid,)).fetchall()
    g = c.execute('SELECT * FROM gestion_sp WHERE tramite_id=?',
                  (tid,)).fetchone()
    admin_name = ''
    if g and g['asignado_por']:
        r = c.execute('SELECT full_name FROM users WHERE id=?',
                      (g['asignado_por'],)).fetchone()
        admin_name = r['full_name'] if r else ''
    gg_name = ''
    if g and g['conformidad_gg']:
        r = c.execute(
            "SELECT full_name FROM users WHERE role='Gerencia General' AND active=1 ORDER BY id LIMIT 1").fetchone()
        gg_name = r['full_name'] if r else ''
    c.close()

    moneda = 'US$' if (t['moneda'] or 'PEN') == 'USD' else 'S/'
    total = sum(float(i['monto'] or 0) for i in items)
    base = io.BytesIO()
    styles = getSampleStyleSheet()
    title = ParagraphStyle('LANRTitle', parent=styles['Heading1'], fontName='Helvetica-Bold',
                           fontSize=12, leading=14, alignment=TA_CENTER, textColor=colors.HexColor('#17365D'))
    small = ParagraphStyle(
        'LANRSmall', parent=styles['BodyText'], fontSize=7, leading=9)
    tiny = ParagraphStyle(
        'LANRTiny', parent=styles['BodyText'], fontSize=6.5, leading=8)
    doc = SimpleDocTemplate(base, pagesize=A4, rightMargin=16*mm,
                            leftMargin=16*mm, topMargin=14*mm, bottomMargin=14*mm)
    story = []
    logo = os.path.join(BASE, 'static', 'logo-lanr.jpeg')
    if os.path.exists(logo):
        story.append(Image(logo, width=36*mm, height=14*mm))
    story.append(Paragraph('SOLICITUD DE PAGO', title))
    story.append(Paragraph(f'N° {t["numero"]}', ParagraphStyle(
        'num', parent=title, fontSize=9)))
    story.append(Spacer(1, 4*mm))
    story.append(Paragraph(str(t['proyecto'] or ''), small))
    story.append(Spacer(1, 3*mm))

    info = [['TIPO DE SOLICITUD', t['subtipo'] or ''],
            ['BENEFICIARIO', t['beneficiario'] or '']]
    if t['dni_ruc']:
        info.append(['DNI / RUC', t['dni_ruc']])
    if modalidades:
        info.append(['MODALIDAD DE PAGO', ' / '.join(r['modalidad']
                    for r in modalidades)])
    for ct in cuentas:
        if ct['banco']:
            info.append(
                ['BANCO', f"{ct['banco']}{' · '+ct['cuenta_cci'] if ct['cuenta_cci'] else ''}"])
    if t['responsable']:
        info.append(['RESPONSABLE', t['responsable']])
    if t['celular']:
        info.append(['N° CELULAR', t['celular']])
    info.append(['FECHA', t['fecha'] or ''])
    if t['fecha_limite_pago']:
        info.append(['FECHA LÍMITE DE PAGO', t['fecha_limite_pago']])
    ti = Table([[Paragraph(str(a), tiny), Paragraph(str(b), tiny)]
               for a, b in info], colWidths=[44*mm, 118*mm])
    ti.setStyle(TableStyle([('GRID', (0, 0), (-1, -1), 0.4, colors.HexColor('#9CA3AF')), ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#E5E7EB')), ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
                ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('LEFTPADDING', (0, 0), (-1, -1), 4), ('RIGHTPADDING', (0, 0), (-1, -1), 4), ('TOPPADDING', (0, 0), (-1, -1), 3), ('BOTTOMPADDING', (0, 0), (-1, -1), 3)]))
    story.append(ti)
    story.append(Spacer(1, 4*mm))

    data = [['ÍTEM', 'CONCEPTO', 'N° DESP.',
             'UND', 'CANT.', 'C. UNIT.', 'MONTO']]
    negrows = []
    for idx, i in enumerate(items, 1):
        q = float(i['cantidad'] or 0)
        cost = float(i['costo'] or 0)
        m = float(i['monto'] or 0)
        data.append([str(i['nro'] or idx), Paragraph(str(i['descripcion'] or ''), tiny), i['nro_despacho']
                    or '', i['unidad'] or '', f'{q:g}', f'{moneda} {cost:,.2f}', f'{moneda} {m:,.2f}'])
        if q < 0:
            negrows.append(idx)
    tb = Table(data, colWidths=[10*mm, 65*mm, 18*mm,
               13*mm, 16*mm, 24*mm, 25*mm], repeatRows=1)
    st = [('GRID', (0, 0), (-1, -1), 0.35, colors.HexColor('#9CA3AF')), ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#D9EAF7')), ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
          ('FONTSIZE', (0, 0), (-1, -1), 6.2), ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('ALIGN', (0, 0), (0, -1), 'CENTER'), ('ALIGN', (2, 1), (-1, -1), 'CENTER')]
    for r in negrows:
        st.append(('TEXTCOLOR', (4, r), (-1, r), colors.red))
    tb.setStyle(TableStyle(st))
    story.append(tb)
    story.append(Spacer(1, 3*mm))

    totals = [['TOTAL', f'{moneda} {total:,.2f}']]
    if float(t['amortizacion'] or 0) > 0:
        totals.append(['SOLICITUD DE PAGO (AMORTIZACIÓN)',
                      f'{moneda} {float(t["amortizacion"]):,.2f}'])
        totals.append(['SALDO PENDIENTE DE PAGO',
                      f'{moneda} {float(t["saldo_pendiente"] or 0):,.2f}'])
    totals.append(['ABONO', f'{moneda} {float(t["abono"] or 0):,.2f}'])
    tt = Table(totals, colWidths=[110*mm, 52*mm])
    tt.setStyle(TableStyle([('GRID', (0, 0), (-1, -1), 0.4, colors.HexColor('#9CA3AF')), ('BACKGROUND', (0, 0), (0, -1), colors.HexColor(
        '#E5E7EB')), ('FONTNAME', (0, 0), (-1, -1), 'Helvetica-Bold'), ('ALIGN', (1, 0), (1, -1), 'RIGHT'), ('FONTSIZE', (0, 0), (-1, -1), 7)]))
    story.append(tt)
    if t['observaciones']:
        story.extend([Spacer(
            1, 3*mm), Paragraph('<b>OBSERVACIONES:</b> '+str(t['observaciones']), small)])
    if comps:
        story.extend(
            [Spacer(1, 3*mm), Paragraph('<b>COMPROBANTES REGISTRADOS</b>', small)])
        for cp in comps:
            label = ' · '.join(
                [x for x in [cp['tipo'], cp['numero']] if x]) or 'Comprobante'
            story.append(Paragraph('• '+label, tiny))
    story.append(Spacer(1, 8*mm))
    art = next((a for a in aps if a['rol'] == 'Gerencia de Obra'), None)
    sig = [['GERENCIA DE OBRA', 'ADMINISTRACIÓN', 'GERENCIA GENERAL'], [art['nombre']
                                                                        if art and art['aprobado'] else 'PENDIENTE', admin_name or 'PENDIENTE', gg_name or 'PENDIENTE']]
    ts = Table(sig, colWidths=[54*mm]*3, rowHeights=[8*mm, 14*mm])
    ts.setStyle(TableStyle([('GRID', (0, 0), (-1, -1), 0.4, colors.HexColor('#9CA3AF')), ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#E5E7EB')), ('FONTNAME',
                (0, 0), (-1, 0), 'Helvetica-Bold'), ('ALIGN', (0, 0), (-1, -1), 'CENTER'), ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('FONTSIZE', (0, 0), (-1, -1), 6.5)]))
    story.append(ts)
    doc.build(story)
    base.seek(0)

    writer = PdfWriter()
    br = PdfReader(base)
    for pg in br.pages:
        writer.add_page(pg)
    anexos = []
    for cp in comps:
        if cp['nombre_archivo']:
            anexos.append(cp['nombre_archivo'])
    for a in adjuntos:
        anexos.append(a['nombre_archivo'])
    for name in anexos:
        path = os.path.join(UPLOADS, name)
        if not os.path.exists(path):
            continue
        try:
            if name.lower().endswith('.pdf'):
                rr = PdfReader(path)
                for pg in rr.pages:
                    writer.add_page(pg)
            elif name.lower().endswith(('.png', '.jpg', '.jpeg', '.webp')):
                ib = io.BytesIO()
                cc = canvas.Canvas(ib, pagesize=A4)
                W, H = A4
                img = ImageReader(path)
                iw, ih = img.getSize()
                scale = min((W-30*mm)/iw, (H-30*mm)/ih)
                dw, dh = iw*scale, ih*scale
                cc.drawImage(img, (W-dw)/2, (H-dh)/2, width=dw,
                             height=dh, preserveAspectRatio=True, mask='auto')
                cc.save()
                ib.seek(0)
                rr = PdfReader(ib)
                for pg in rr.pages:
                    writer.add_page(pg)
        except Exception as e:
            print('No se pudo anexar a SP:', path, e)
    out = io.BytesIO()
    writer.write(out)
    out.seek(0)
    safe_fecha = str(t['fecha'] or '').replace('/', '-')
    return out, f'SP LANR {t["numero"]} {safe_fecha}.pdf'


# =========================================================
# ESQUEMA INTEGRAL V4 - SP / REEMBOLSOS / ÓRDENES
# =========================================================
def ensure_integral_schema():
    c = db()

    def add_column(table, name, definition):
        cols = {r['name']
                for r in c.execute(f'PRAGMA table_info({table})').fetchall()}
        if name not in cols:
            c.execute(f'ALTER TABLE {table} ADD COLUMN {name} {definition}')

    add_column('tramites', 'celular', 'TEXT')
    add_column('tramites', 'moneda', "TEXT DEFAULT 'PEN'")
    add_column('tramites', 'fecha_limite_pago', 'TEXT')
    add_column('tramites', 'amortizacion', 'REAL DEFAULT 0')
    add_column('tramites', 'saldo_pendiente', 'REAL DEFAULT 0')
    add_column('items', 'nro_despacho', 'TEXT')

    c.executescript('''
    CREATE TABLE IF NOT EXISTS sp_modalidades(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        modalidad TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS sp_cuentas(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        banco TEXT NOT NULL,
        cuenta_cci TEXT
    );
    CREATE TABLE IF NOT EXISTS sp_comprobantes(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        tipo TEXT,
        numero TEXT,
        nombre_original TEXT,
        nombre_archivo TEXT,
        orden INTEGER DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS sp_pagos_multiples(
        id INTEGER PRIMARY KEY,
        tramite_id INTEGER NOT NULL,
        pagado_por INTEGER NOT NULL,
        medio_pago TEXT NOT NULL,
        banco_pago TEXT,
        nro_operacion TEXT,
        monto REAL NOT NULL,
        fecha_pago TEXT NOT NULL,
        nombre_original TEXT NOT NULL,
        nombre_archivo TEXT NOT NULL,
        creado TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS reembolsos_rendiciones(
        id INTEGER PRIMARY KEY,
        obra_id INTEGER NOT NULL,
        tipo TEXT NOT NULL,
        numero TEXT,
        fecha TEXT NOT NULL,
        solicitante_id INTEGER NOT NULL,
        concepto TEXT NOT NULL,
        monto REAL NOT NULL,
        moneda TEXT DEFAULT 'PEN',
        observaciones TEXT,
        estado TEXT DEFAULT 'Pendiente de Administración',
        autorizado_por INTEGER,
        fecha_autorizacion TEXT,
        atendido_por INTEGER,
        fecha_atencion TEXT,
        creado TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS reembolso_adjuntos(
        id INTEGER PRIMARY KEY,
        reembolso_id INTEGER NOT NULL,
        tipo TEXT DEFAULT 'Sustento',
        nombre_original TEXT NOT NULL,
        nombre_archivo TEXT NOT NULL,
        creado TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS ordenes(
        id INTEGER PRIMARY KEY,
        obra_id INTEGER NOT NULL,
        tipo_orden TEXT NOT NULL,
        numero TEXT NOT NULL,
        fecha TEXT NOT NULL,
        proveedor_id INTEGER,
        proveedor_nombre TEXT NOT NULL,
        documento TEXT,
        moneda TEXT DEFAULT 'PEN',
        descripcion TEXT,
        total REAL DEFAULT 0,
        estado TEXT DEFAULT 'Borrador',
        creador INTEGER NOT NULL,
        creado TEXT NOT NULL,
        vb_gerencia_obra_por INTEGER,
        vb_gerencia_obra_fecha TEXT,
        vb_administracion_por INTEGER,
        vb_administracion_fecha TEXT,
        vb_gerencia_general_por INTEGER,
        vb_gerencia_general_fecha TEXT
    );
    CREATE TABLE IF NOT EXISTS orden_items(
        id INTEGER PRIMARY KEY,
        orden_id INTEGER NOT NULL,
        nro INTEGER,
        descripcion TEXT NOT NULL,
        unidad TEXT,
        cantidad REAL NOT NULL,
        precio_unitario REAL NOT NULL,
        monto REAL NOT NULL
    );
    CREATE TABLE IF NOT EXISTS orden_adjuntos(
        id INTEGER PRIMARY KEY,
        orden_id INTEGER NOT NULL,
        nombre_original TEXT NOT NULL,
        nombre_archivo TEXT NOT NULL,
        creado TEXT NOT NULL
    );
    ''')
    # Compatibilidad con bases creadas antes de esta versión integral.
    order_cols = {r['name']
                  for r in c.execute("PRAGMA table_info(ordenes)").fetchall()}
    for col, ddl in (
        ('vb_gerencia_obra_por', 'INTEGER'),
        ('vb_gerencia_obra_fecha', 'TEXT'),
        ('vb_administracion_por', 'INTEGER'),
        ('vb_administracion_fecha', 'TEXT'),
        ('vb_gerencia_general_por', 'INTEGER'),
        ('vb_gerencia_general_fecha', 'TEXT'),
    ):
        if col not in order_cols:
            c.execute(f"ALTER TABLE ordenes ADD COLUMN {col} {ddl}")
    c.commit()
    c.close()


@app.route('/', methods=['GET', 'POST'])
def login():

    # Si ya existe una sesión,
    # no volver a mostrar el login.
    if request.method == 'GET' and 'uid' in session:
        return redirect(
            url_for('home')
        )

    if request.method == 'POST':

        name = request.form.get(
            'username',
            ''
        ).strip()

        pwd = request.form.get(
            'password',
            ''
        )

        c = db()

        u = c.execute(
            '''
            SELECT *
            FROM users
            WHERE username = ? COLLATE BINARY
            ''',
            (name,)
        ).fetchone()

        # ==========================================
        # VALIDAR CREDENCIALES
        # ==========================================

        if not u or not check_password_hash(
            u['password_hash'],
            pwd
        ):

            c.close()

            flash(
                'Usuario o contraseña incorrectos.',
                'danger'
            )

            return render_template(
                'login.html'
            )

        # ==========================================
        # VALIDAR CUENTA ACTIVA
        # ==========================================

        if not u['active']:

            c.close()

            flash(
                'Su cuenta está inactiva. Contacte a Sistemas.',
                'danger'
            )

            return render_template(
                'login.html'
            )

        # ==========================================
        # REGISTRAR ÚLTIMO INICIO DE SESIÓN
        # ==========================================

        now = datetime.now().strftime(
            '%Y-%m-%d %H:%M:%S'
        )

        c.execute(
            '''
            UPDATE users
            SET last_login=?
            WHERE id=?
            ''',
            (
                now,
                u['id']
            )
        )

        c.commit()
        c.close()

        # ==========================================
        # CREAR SESIÓN
        # ==========================================

        session.permanent = True
        session['uid'] = u['id']
        session.pop('obra_id', None)

        return redirect(
            url_for('home')
        )

    return render_template(
        'login.html'
    )


@app.route('/project/select/<int:obra_id>', methods=['POST'])
@login_required
def select_project(obra_id):

    c = db()

    obra = c.execute(
        '''
        SELECT o.id
        FROM obras o

        JOIN usuario_obra uo
            ON uo.obra_id = o.id

        WHERE o.id=?
        AND uo.usuario_id=?
        AND o.active=1
        AND uo.active=1
        ''',
        (
            obra_id,
            session['uid']
        )
    ).fetchone()

    c.close()

    if not obra:
        abort(403)

    session['obra_id'] = obra['id']

    return redirect(
        request.referrer or url_for('home')
    )


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
            'Si el correo está registrado, recibirá un enlace para restablecer su contraseña.',
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

        # ==========================================
        # VALIDAR NUEVA CONTRASEÑA
        # ==========================================

        valida, mensaje = validate_password(
            nueva
        )

        if not valida:

            flash(
                mensaje,
                'danger'
            )

            return render_template(
                'reset_password.html',
                token=token
            )

        # ==========================================
        # CONFIRMAR CONTRASEÑA
        # ==========================================

        if nueva != confirmar:

            flash(
                'Las contraseñas no coinciden.',
                'danger'
            )

            return render_template(
                'reset_password.html',
                token=token
            )

        # ==========================================
        # NO PERMITIR LA CONTRASEÑA ANTERIOR
        # ==========================================

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

        # ==========================================
        # GUARDAR NUEVA CONTRASEÑA
        # ==========================================

        c = db()

        c.execute(
            '''
            UPDATE users
            SET password_hash=?,
                updated_at=?
            WHERE id=?
            ''',
            (
                generate_password_hash(nueva),
                datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                usuario['id']
            )
        )

        c.commit()
        c.close()

        flash(
            'Contraseña restablecida correctamente. Ya puede iniciar sesión.',
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

        actual = request.form.get(
            'current',
            ''
        )

        nueva = request.form.get(
            'new',
            ''
        )

        confirmar = request.form.get(
            'confirm',
            ''
        )

        # ==========================================
        # CONTRASEÑA ACTUAL
        # ==========================================

        if not check_password_hash(
            u['password_hash'],
            actual
        ):

            flash(
                'La contraseña actual es incorrecta.',
                'danger'
            )

            return render_template(
                'change_password.html',
                user=None
            )

        # ==========================================
        # NUEVA CONTRASEÑA
        # ==========================================

        valida, mensaje = validate_password(
            nueva
        )

        if not valida:

            flash(
                mensaje,
                'danger'
            )

            return render_template(
                'change_password.html',
                user=None
            )

        # ==========================================
        # CONFIRMACIÓN
        # ==========================================

        if nueva != confirmar:

            flash(
                'Las contraseñas no coinciden.',
                'danger'
            )

            return render_template(
                'change_password.html',
                user=None
            )

        # ==========================================
        # NO REPETIR CONTRASEÑA ACTUAL
        # ==========================================

        if check_password_hash(
            u['password_hash'],
            nueva
        ):

            flash(
                'La nueva contraseña debe ser diferente a la contraseña actual.',
                'danger'
            )

            return render_template(
                'change_password.html',
                user=None
            )

        # ==========================================
        # GUARDAR
        # ==========================================

        c = db()

        c.execute(
            '''
            UPDATE users
            SET password_hash=?,
                updated_at=?
            WHERE id=?
            ''',
            (
                generate_password_hash(nueva),
                datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                u['id']
            )
        )

        c.commit()
        c.close()

        flash(
            'Contraseña actualizada correctamente.',
            'success'
        )

        return redirect(
            url_for('home')
        )

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
    role = u['role']

    # ============================
    # CONTADORES
    # ============================

    obra = active_project()
    tramites_activos = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE obra_id=? AND estado!='Cerrado'
        ''',
        (obra['id'],)
    ).fetchall() if obra else []
    activos = sum(
        1 for t in tramites_activos if _can_user_view_tramite(c, t, u))

    # V.°B.° formal inicial: José Luis y Artidoro mantienen control cruzado
    # tanto en REQ como en SP.
    roles_con_visto_bueno = ('Gerencia de Obra', 'Control y Planeamiento')

    aprobaciones_pendientes_count = 0
    if role in roles_con_visto_bueno:
        aprobaciones_pendientes_count = c.execute(
            '''
            SELECT COUNT(*) c
            FROM approvals a
            JOIN tramites t ON t.id = a.tramite_id
            WHERE a.usuario_id=?
            AND a.aprobado=0
            AND t.estado='Pendiente de aprobación'
            ''',
            (u['id'],)
        ).fetchone()['c']

    counts = {
        'activos': activos,
        'aprob': aprobaciones_pendientes_count,

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

    pendientes_aprobacion = []

    if role in roles_con_visto_bueno:
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
            AND t.estado = 'Pendiente de aprobación'

            ORDER BY t.id DESC
            ''',
            (u['id'],)
        ).fetchall()

    # ============================================
    # PENDIENTES DE GESTIÓN LOGÍSTICA - RODRIGO
    # ============================================

    pendientes_logistica = []

    if role == 'Logística':

        pendientes_logistica = c.execute(
            '''
            SELECT
                t.id,
                t.tracking,
                t.numero,
                t.fecha,
                t.estado,
                u.full_name AS creador_nombre,
                COALESCE((
                    SELECT CASE MIN(CASE COALESCE(i2.prioridad,'Normal') WHEN 'Urgente' THEN 0 WHEN 'Prioritario' THEN 1 ELSE 2 END)
                        WHEN 0 THEN 'Urgente' WHEN 1 THEN 'Prioritario' ELSE 'Normal' END
                    FROM items i2 WHERE i2.tramite_id=t.id
                ), 'Normal') AS prioridad_maxima,
                (SELECT MIN(NULLIF(i3.fecha_requerida,'')) FROM items i3 WHERE i3.tramite_id=t.id) AS fecha_requerida_min,

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

                    WHEN t.estado IN ('Pendiente de cotización','Cotizaciones en gestión')
                        THEN 'Cotizaciones'

                    WHEN t.estado = 'Recibido por Logística'
                        THEN 'Cotizaciones'

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
                'Pendiente de cotización',
                'Cotizaciones en gestión',
                'En gestión de compra'
            )

            ORDER BY
                CASE COALESCE((SELECT CASE MIN(CASE COALESCE(i4.prioridad,'Normal') WHEN 'Urgente' THEN 0 WHEN 'Prioritario' THEN 1 ELSE 2 END) WHEN 0 THEN 'Urgente' WHEN 1 THEN 'Prioritario' ELSE 'Normal' END FROM items i4 WHERE i4.tramite_id=t.id), 'Normal')
                    WHEN 'Urgente' THEN 0 WHEN 'Prioritario' THEN 1 ELSE 2 END,
                CASE WHEN (SELECT MIN(NULLIF(i5.fecha_requerida,'')) FROM items i5 WHERE i5.tramite_id=t.id) IS NULL THEN 1 ELSE 0 END,
                (SELECT MIN(NULLIF(i6.fecha_requerida,'')) FROM items i6 WHERE i6.tramite_id=t.id) ASC,
                t.id DESC
            '''
        ).fetchall()

    pendientes_envio = 0

    if role == 'Logística':
        pendientes_envio = sum(
            1
            for x in pendientes_logistica
            if x['pendiente_de'] == 'Guía y envío a obra'
        )

    # ============================================
    # PENDIENTES DE TESORERÍA - YOANA
    # ============================================

    pendientes_tesoreria = []
    reembolsos_tesoreria = []
    sp_tesoreria = []

    if role == 'Tesorería':

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

            AND s.origen = 'REQ-Compra'

            ORDER BY s.id DESC
            '''
        ).fetchall()

        # Los reembolsos NO forman parte de la bandeja principal de pagos.
        # Se gestionan desde la pantalla Tesorería.
        reembolsos_tesoreria = c.execute(
            '''
            SELECT s.id
            FROM solicitudes_tesoreria s
            JOIN tramites t ON t.id = s.tramite_id
            WHERE s.estado = 'Pendiente'
              AND s.origen = 'REQ-Reembolso'
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

    # Administración revisa las SP que ya tienen el V°B° de obra requerido
    # y decide si pagará Tesorería o Gerencia General.
    if role == 'Administración':
        sp_pendientes_asignacion = c.execute(
            '''
            SELECT t.id,t.tracking,t.numero,t.fecha,t.estado,t.abono,t.moneda,
                   creador.full_name AS creador_nombre
            FROM tramites t
            JOIN users creador ON creador.id=t.creador
            WHERE t.tipo='SP' AND t.estado='Pendiente de Administración'
            ORDER BY t.id DESC
            '''
        ).fetchall()

    if role == 'Gerencia General':
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
        fecha_hoy=datetime.now().strftime('%Y-%m-%d'),
        pendientes_envio=pendientes_envio,
        pendientes_tesoreria=pendientes_tesoreria,
        reembolsos_tesoreria=reembolsos_tesoreria,
        sp_tesoreria=sp_tesoreria,
        sp_pendientes_asignacion=sp_pendientes_asignacion,
        sp_pendientes_conformidad=sp_pendientes_conformidad
    )


def get_active_units(c):
    rows = c.execute(
        '''
        SELECT abreviatura, uso
        FROM unidades_catalogo
        WHERE active=1
        ORDER BY abreviatura COLLATE NOCASE
        '''
    ).fetchall()

    req_units = [r['abreviatura']
                 for r in rows if r['uso'] in ('REQ', 'AMBOS')]
    sp_units = [r['abreviatura'] for r in rows if r['uso'] in ('SP', 'AMBOS')]
    return req_units, sp_units


@app.route('/requests/new')
@login_required
def new_request():
    u = user()
    if not can_create_request(u):
        abort(403)
    obra = active_project()
    if not obra:
        flash('No tienes una obra activa asignada.', 'danger')
        return redirect(url_for('home'))
    return render_template(
        'new_request.html', user=u, obra=obra,
        can_create_req=can_create_requirement(u),
        can_create_sp=can_create_payment_request(u)
    )


@app.route('/requests/new/requirement')
@login_required
def new_requirement():
    u = user()
    if not can_create_requirement(u):
        abort(403)
    obra = active_project()
    if not obra:
        flash('No tienes una obra activa asignada.', 'danger')
        return redirect(url_for('home'))
    c = db()
    req_units, _ = get_active_units(c)
    year = datetime.now().year
    num_info = get_form_number_info(c, obra['id'], 'REQ', year)
    c.close()
    return render_template(
        'new_requirement.html', user=u, obra=obra, project=obra['proyecto'],
        place=obra['ubicacion'], req_units=req_units,
        office_req_only=u['role'] in OFFICE_REQUIREMENT_ROLES,
        current_year=year, number_info=num_info
    )


@app.route('/requests/new/payment')
@login_required
def new_payment_request():
    u = user()
    if not can_create_payment_request(u):
        abort(403)
    obra = active_project()
    if not obra:
        flash('No tienes una obra activa asignada.', 'danger')
        return redirect(url_for('home'))
    c = db()
    _, sp_units = get_active_units(c)
    year = datetime.now().year
    num_info = get_form_number_info(c, obra['id'], 'SP', year)
    c.close()
    return render_template(
        'new_payment_request.html', user=u, obra=obra, project=obra['proyecto'],
        place=obra['ubicacion'], sp_units=sp_units, current_year=year,
        number_info=num_info
    )


@app.route('/requests/number-info/<tipo>')
@login_required
def request_number_info(tipo):
    tipo = tipo.upper()
    if tipo not in ('REQ', 'SP'):
        abort(404)
    u = user()
    if tipo == 'REQ' and not can_create_requirement(u):
        abort(403)
    if tipo == 'SP' and not can_create_payment_request(u):
        abort(403)
    obra = active_project()
    if not obra:
        abort(403)
    try:
        year = int(request.args.get('year', datetime.now().year))
    except (TypeError, ValueError):
        year = datetime.now().year
    c = db()
    info = get_form_number_info(c, obra['id'], tipo, year)
    sequence = request.args.get('sequence', '').strip()
    info['exists'] = False
    if sequence:
        try:
            full_number = compose_form_number(tipo, sequence, year)
            info['exists'] = c.execute(
                '''
                SELECT 1 FROM tramites
                WHERE obra_id=? AND tipo=?
                  AND LOWER(TRIM(numero))=LOWER(TRIM(?))
                LIMIT 1
                ''',
                (obra['id'], tipo, full_number)
            ).fetchone() is not None
            info['full_number'] = full_number
        except ValueError:
            info['invalid'] = True
    c.close()
    return jsonify(info)


@app.route('/requirements/create', methods=['POST'])
@login_required
def create_requirement():
    u = user()

    if not can_create_requirement(u):
        abort(403)

    fecha = request.form['fecha']
    year = fecha[:4]
    try:
        numero = compose_form_number(
            'REQ', request.form.get('numero_correlativo'), year)
    except ValueError as exc:
        flash(str(exc), 'warning')
        return redirect(url_for('new_requirement'))

    obra = active_project()

    if not obra:
        flash(
            'No tienes una obra activa asignada.',
            'danger'
        )
        return redirect(url_for('home'))

    c = db()

    existe_numero = c.execute(
        '''
        SELECT id
        FROM tramites
        WHERE obra_id=?
          AND tipo='REQ'
          AND LOWER(TRIM(numero)) = LOWER(TRIM(?))
        LIMIT 1
        ''',
        (obra['id'], numero)
    ).fetchone()

    if existe_numero:
        info = get_form_number_info(c, obra['id'], 'REQ', year)
        c.close()
        ultimo = f"{info['last']}-{year}" if info['last'] is not None else 'sin registros previos'
        flash(
            f'El requerimiento N.° {numero} ya existe. El último número registrado es {ultimo}. Modifique el correlativo e inténtelo nuevamente.',
            'warning'
        )
        return redirect(url_for('new_requirement'))

    tracking = next_code(
        'REQ',
        year,
        obra['id']
    )

    primero = re.sub(r'\D', '', numero.split('-')[0]) or numero.split('-')[0]
    formato = 'F01A-LANR-'+primero
    cur = c.execute(
        '''
        INSERT INTO tramites(
            obra_id,
            tracking,
            tipo,
            numero,
            fecha,
            proyecto,
            lugar,
            estado,
            formato,
            creador,
            creado
        )
        VALUES(?,?,?,?,?,?,?,?,?,?,?)
        ''',
        (
            obra['id'],
            tracking,
            'REQ',
            numero,
            fecha,
            obra['proyecto'],
            obra['ubicacion'],
            'Pendiente de mi revisión',
            formato,
            u['id'],
            datetime.now().strftime(
                '%Y-%m-%d %H:%M:%S'
            )
        )
    )
    tid = cur.lastrowid
    secs = request.form.getlist('seccion[]')
    desc = request.form.getlist('descripcion[]')
    qty = request.form.getlist('cantidad[]')
    und = request.form.getlist('unidad[]')
    stock = request.form.getlist('stock[]')
    just = request.form.getlist('justificacion[]')
    prioridades = request.form.getlist('prioridad_item[]')
    fechas_req = request.form.getlist('fecha_requerida_item[]')
    item_keys = request.form.getlist('item_key[]')

    req_items = []

    for i, descripcion in enumerate(desc):
        descripcion = descripcion.strip()

        # Fila agregada accidentalmente y sin descripción: ignorar.
        if not descripcion:
            continue

        cantidad_txt = qty[i].strip() if i < len(qty) else ''
        unidad = und[i].strip() if i < len(und) else ''
        stock_txt = stock[i].strip() if i < len(stock) else ''
        justificacion = just[i].strip() if i < len(just) else ''
        seccion = secs[i] if i < len(secs) else ''
        prioridad_item = prioridades[i].strip(
        ) if i < len(prioridades) else 'Normal'
        if prioridad_item not in ('Normal', 'Prioritario', 'Urgente'):
            prioridad_item = 'Normal'
        fecha_requerida_item = fechas_req[i].strip(
        ) if i < len(fechas_req) else ''
        fecha_requerida_item = fecha_requerida_item or None
        item_key = item_keys[i].strip() if i < len(item_keys) else ''
        item_key = item_key or uuid.uuid4().hex

        if not cantidad_txt:
            c.rollback()
            c.close()
            flash('Todo material con descripción debe tener cantidad.', 'danger')
            return redirect(url_for('new_requirement'))

        cantidad = parse_decimal(cantidad_txt)

        if cantidad <= 0:
            c.rollback()
            c.close()
            flash('La cantidad de cada material debe ser mayor que 0.', 'danger')
            return redirect(url_for('new_requirement'))

        if not unidad:
            c.rollback()
            c.close()
            flash('Todo material con descripción debe tener unidad.', 'danger')
            return redirect(url_for('new_requirement'))

        stock_actual = parse_decimal(stock_txt) if stock_txt else 0

        req_items.append(
            (
                seccion,
                descripcion,
                unidad,
                cantidad,
                stock_actual,
                justificacion,
                prioridad_item,
                fecha_requerida_item,
                item_key
            )
        )

    if u['role'] in OFFICE_REQUIREMENT_ROLES:
        if any(item[0] != 'Útiles de oficina' for item in req_items):
            c.rollback()
            c.close()
            flash(
                'Los requerimientos de Administración, Logística, Tesorería y Sistemas solo pueden registrarse como Útiles de oficina.',
                'danger'
            )
            return redirect(url_for('new_requirement'))

    if not req_items:
        c.rollback()
        c.close()
        flash(
            'Agregue al menos un material con descripción, cantidad y unidad.',
            'danger'
        )
        return redirect(url_for('new_requirement'))

    nro_global = 0

    for seccion, descripcion, unidad, cantidad, stock_actual, justificacion, prioridad_item, fecha_requerida_item, item_key in req_items:
        nro_global += 1

        c.execute(
            'INSERT INTO items('
            'tramite_id,seccion,nro,descripcion,unidad,cantidad,stock,comprar,justificacion,prioridad,fecha_requerida,item_key'
            ') VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
            (
                tid,
                seccion,
                nro_global,
                descripcion,
                unidad,
                cantidad,
                stock_actual,
                max(cantidad - stock_actual, 0),
                justificacion,
                prioridad_item,
                fecha_requerida_item,
                item_key
            )
        )

        # Imágenes opcionales asociadas exactamente a este ítem. Máximo 5.
        archivos_imagen = [a for a in request.files.getlist(
            f'imagenes_{item_key}') if a and a.filename]
        if len(archivos_imagen) > 5:
            c.rollback()
            c.close()
            flash(
                f'El ítem {nro_global:02d} admite como máximo 5 imágenes de referencia.', 'danger')
            return redirect(url_for('new_requirement'))
        for archivo in archivos_imagen:
            if not archivo or not archivo.filename:
                continue
            ext = os.path.splitext(archivo.filename)[1].lower()
            if ext not in ('.jpg', '.jpeg', '.png', '.webp'):
                continue
            nombre_seguro = secure_filename(archivo.filename)
            guardado = f'req_item_{tid}_{item_key}_{uuid.uuid4().hex[:10]}{ext}'
            archivo.save(os.path.join(UPLOADS, guardado))
            c.execute(
                '''INSERT INTO item_imagenes(
                    tramite_id,item_key,nombre_original,nombre_archivo,creado
                ) VALUES(?,?,?,?,?)''',
                (tid, item_key, nombre_seguro, guardado,
                 datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            )

    # Copia externa opcional del mismo requerimiento.
    # Puede adjuntarse el PDF o Excel elaborado fuera del sistema como respaldo;
    # no reemplaza el registro ni el PDF generado por LANR.
    # El requerimiento admite UN SOLO archivo externo (PDF o Excel).
    documentos_req = [f for f in request.files.getlist(
        'documentos') if f and f.filename]
    if documentos_req:
        archivo = documentos_req[0]
        nombre_original = archivo.filename.strip()
        ext = os.path.splitext(nombre_original)[1].lower()
        if ext in ('.pdf', '.xls', '.xlsx', '.xlsm'):
            nombre_seguro_original = secure_filename(nombre_original)
            nombre_guardado = (
                f"req_{tid}_adj_{datetime.now().strftime('%Y%m%d%H%M%S%f')}_"
                f"1_{nombre_seguro_original}"
            )
            archivo.save(os.path.join(UPLOADS, nombre_guardado))
            c.execute(
                '''INSERT INTO attachments(
                    tramite_id,nombre_original,nombre_archivo,orden,fecha
                ) VALUES(?,?,?,?,?)''',
                (tid, nombre_original, nombre_guardado, 1,
                 datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            )

    for user_role, approval_role in [
        ('Control y Planeamiento', 'Planeamiento'),
        ('Gerencia de Obra', 'Gerencia de Obra')
    ]:

        approver = c.execute(
            '''
            SELECT u.id
            FROM users u
            JOIN usuario_obra uo
                ON uo.usuario_id = u.id
            WHERE u.role=?
            AND u.active=1
            AND uo.obra_id=?
            AND uo.active=1
            ORDER BY u.id
            LIMIT 1
            ''',
            (
                user_role,
                obra['id']
            )
        ).fetchone()

        if not approver:
            c.rollback()
            c.close()
            flash(
                f'La obra no tiene un usuario activo con el rol "{user_role}".',
                'danger'
            )
            return redirect(url_for('new_requirement'))

        c.execute(
            '''
            INSERT INTO approvals(
                tramite_id,
                rol,
                usuario_id
            )
            VALUES(?,?,?)
            ''',
            (
                tid,
                approval_role,
                approver['id']
            )
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
    if not can_create_payment_request(u):
        abort(403)
    obra = active_project()
    if not obra:
        flash('No tienes una obra activa asignada.', 'danger')
        return redirect(url_for('home'))

    fecha = request.form.get('fecha', '').strip()
    beneficiario = request.form.get('beneficiario', '').strip()
    subtipo = request.form.get('subtipo', '').strip()
    if subtipo not in ('Proveedor persona natural', 'Proveedor persona jurídica', 'Cuarta categoría / Recibo por Honorarios', 'Planillas'):
        flash('Seleccione un tipo de solicitud válido.', 'danger')
        return redirect(url_for('new_payment_request'))
    if not fecha or not beneficiario:
        flash('Fecha y beneficiario son obligatorios.', 'danger')
        return redirect(url_for('new_payment_request'))
    try:
        numero = compose_form_number(
            'SP', request.form.get('numero_correlativo'), fecha[:4])
    except ValueError as exc:
        flash(str(exc), 'warning')
        return redirect(url_for('new_payment_request'))

    moneda = request.form.get('moneda', 'PEN')
    if moneda not in ('PEN', 'USD'):
        moneda = 'PEN'
    dni_ruc = request.form.get('dni_ruc', '').strip()
    responsable = request.form.get('responsable', '').strip()
    celular = re.sub(r'\D', '', request.form.get('celular', ''))[:15]
    observaciones = request.form.get('observaciones', '').strip()
    fecha_limite = request.form.get('fecha_limite_pago', '').strip() or None

    conceptos = request.form.getlist('concepto[]')
    unidades = request.form.getlist('unidad_sp[]')
    cantidades = request.form.getlist('cantidad_sp[]')
    costos = request.form.getlist('costo[]')
    despachos = request.form.getlist('nro_despacho[]')
    parsed = []
    for i, concepto in enumerate(conceptos):
        concepto = concepto.strip()
        if not concepto:
            continue
        try:
            cantidad = parse_decimal(cantidades[i])
            costo = parse_decimal(costos[i])
        except Exception:
            flash('Revise las cantidades y los costos de los ítems.', 'danger')
            return redirect(url_for('new_payment_request'))
        if costo < 0:
            flash('El costo unitario no puede ser negativo.', 'danger')
            return redirect(url_for('new_payment_request'))
        unidad = unidades[i].strip() if i < len(unidades) else ''
        if not unidad:
            flash('Todo concepto debe tener unidad.', 'danger')
            return redirect(url_for('new_payment_request'))
        despacho = despachos[i].strip() if i < len(despachos) else ''
        parsed.append((concepto, unidad, cantidad,
                      costo, cantidad*costo, despacho))
    if not parsed:
        flash('Agregue al menos un concepto.', 'danger')
        return redirect(url_for('new_payment_request'))
    total = sum(x[4] for x in parsed)
    usar_amort = request.form.get('usar_amortizacion') == '1'
    amort = parse_decimal(request.form.get(
        'amortizacion', '0')) if usar_amort else 0
    if amort < 0 or (total >= 0 and amort > total):
        flash('La amortización debe estar entre 0 y el total.', 'danger')
        return redirect(url_for('new_payment_request'))
    abono = amort if usar_amort else total
    saldo = total-amort if usar_amort else 0

    c = db()
    if c.execute("SELECT 1 FROM tramites WHERE obra_id=? AND tipo='SP' AND LOWER(TRIM(numero))=LOWER(TRIM(?))", (obra['id'], numero)).fetchone():
        c.close()
        flash(f'La solicitud N.° {numero} ya existe.', 'warning')
        return redirect(url_for('new_payment_request'))
    tracking = next_code('SP', fecha[:4], obra['id'])
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cur = c.execute('''INSERT INTO tramites(obra_id,tracking,tipo,subtipo,numero,fecha,proyecto,lugar,beneficiario,dni_ruc,responsable,celular,abono,observaciones,moneda,fecha_limite_pago,amortizacion,saldo_pendiente,estado,creador,creado)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                    (obra['id'], tracking, 'SP', subtipo, numero, fecha, obra['proyecto'], obra['ubicacion'], beneficiario, dni_ruc, responsable, celular, abono, observaciones, moneda, fecha_limite, amort, saldo, 'Pendiente de mi revisión', u['id'], now))
    tid = cur.lastrowid
    for nro, x in enumerate(parsed, 1):
        c.execute('INSERT INTO items(tramite_id,nro,descripcion,unidad,cantidad,costo,monto,nro_despacho) VALUES(?,?,?,?,?,?,?,?)', (tid, nro, *x))

    modalidades = request.form.getlist('modalidad_pago[]')
    otro_modal = request.form.get('modalidad_otro', '').strip()
    if 'Otro' in modalidades and otro_modal:
        modalidades = [m for m in modalidades if m != 'Otro']+[otro_modal]
    for m in dict.fromkeys([m.strip() for m in modalidades if m.strip()]):
        c.execute(
            'INSERT INTO sp_modalidades(tramite_id,modalidad) VALUES(?,?)', (tid, m))
    bancos = request.form.getlist('banco[]')
    cuentas = request.form.getlist('cuenta_cci[]')
    otros = request.form.getlist('banco_otro[]')
    for i, b in enumerate(bancos):
        b = b.strip()
        cuenta = cuentas[i].strip() if i < len(cuentas) else ''
        if b == 'Otro' and i < len(otros) and otros[i].strip():
            b = otros[i].strip()
        if b:
            c.execute(
                'INSERT INTO sp_cuentas(tramite_id,banco,cuenta_cci) VALUES(?,?,?)', (tid, b, cuenta))

    tipos = request.form.getlist('comprobante_tipo[]')
    nums = request.form.getlist('comprobante_numero[]')
    files = request.files.getlist('comprobante_archivo[]')
    maxn = max(len(tipos), len(nums), len(files))
    for i in range(maxn):
        tp = tipos[i].strip() if i < len(tipos) else ''
        num = nums[i].strip() if i < len(nums) else ''
        f = files[i] if i < len(files) else None
        if not tp and not num and not (f and f.filename):
            continue
        orig = guard = ''
        if f and f.filename:
            if not f.filename.lower().endswith(('.pdf', '.jpg', '.jpeg', '.png', '.webp')):
                continue
            orig = f.filename
            guard = secure_filename(
                f'SP_COMP_{tid}_{i+1}_{uuid.uuid4().hex[:8]}_{f.filename}')
            f.save(os.path.join(UPLOADS, guard))
        c.execute('INSERT INTO sp_comprobantes(tramite_id,tipo,numero,nombre_original,nombre_archivo,orden) VALUES(?,?,?,?,?,?)',
                  (tid, tp, num, orig, guard, i+1))

    orden = 1
    for f in request.files.getlist('documentos'):
        if not f or not f.filename or not f.filename.lower().endswith('.pdf'):
            continue
        orig = f.filename
        guard = secure_filename(
            f'SP_DOC_{tid}_{orden}_{uuid.uuid4().hex[:8]}_{f.filename}')
        f.save(os.path.join(UPLOADS, guard))
        c.execute('INSERT INTO attachments(tramite_id,nombre_original,nombre_archivo,orden,fecha) VALUES(?,?,?,?,?)',
                  (tid, orig, guard, orden, now))
        orden += 1

    # Flujo: Artidoro/José Luis mantienen control cruzado cuando uno de ellos crea.
    # Si crea Administración o Contabilidad, solo Gerencia de Obra da V°B° antes de volver a Administración.
    roles = []
    if u['role'] in ('Gerencia de Obra', 'Control y Planeamiento'):
        roles = [('Gerencia de Obra', 'Gerencia de Obra'),
                 ('Control y Planeamiento', 'Planeamiento')]
    else:
        roles = [('Gerencia de Obra', 'Gerencia de Obra')]
    for user_role, approval_role in roles:
        approver = c.execute(
            '''SELECT u.id FROM users u JOIN usuario_obra uo ON uo.usuario_id=u.id WHERE u.role=? AND u.active=1 AND uo.obra_id=? AND uo.active=1 ORDER BY u.id LIMIT 1''', (user_role, obra['id'])).fetchone()
        if not approver:
            c.rollback()
            c.close()
            flash(
                f'La obra no tiene un usuario activo con el rol {user_role}.', 'danger')
            return redirect(url_for('new_payment_request'))
        c.execute('INSERT INTO approvals(tramite_id,rol,usuario_id) VALUES(?,?,?)',
                  (tid, approval_role, approver['id']))
    c.execute('INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)',
              (tid, u['id'], 'Solicitud registrada: '+tracking, now))
    c.commit()
    c.close()
    return redirect(url_for('request_detail', tid=tid))


@app.route('/requests/<int:tid>/creator-approve', methods=['POST'])
@login_required
def creator_approve(tid):
    u = user()
    obra = active_project()

    if not obra:
        abort(403)

    c = db()

    t = c.execute(
        """
        SELECT *
        FROM tramites
        WHERE id=?
        AND obra_id=?
        """,
        (tid, obra['id'])
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if t['creador'] != u['id']:
        c.close()
        abort(403)

    if t['estado'] != 'Pendiente de mi revisión':
        c.close()
        flash('Este trámite ya fue enviado para aprobación.', 'warning')
        return redirect(url_for('request_detail', tid=tid))

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # Si quien crea es José Luis o Artidoro, su revisión propia cuenta como
    # su V.°B.° formal. El otro responsable revisa después.
    own_approval = c.execute(
        """
        SELECT id FROM approvals
        WHERE tramite_id=? AND usuario_id=?
        LIMIT 1
        """,
        (tid, u['id'])
    ).fetchone()
    if own_approval:
        c.execute(
            """UPDATE approvals SET aprobado=1, fecha=? WHERE id=?""",
            (now, own_approval['id'])
        )

    c.execute(
        """
        UPDATE tramites
        SET estado='Pendiente de aprobación'
        WHERE id=?
        """,
        (tid,)
    )

    c.execute(
        """
        INSERT INTO history(
            tramite_id,
            usuario_id,
            accion,
            fecha
        )
        VALUES(?,?,?,?)
        """,
        (
            tid,
            u['id'],
            'Revisión final completada. V°B° del creador registrado y trámite enviado.',
            now
        )
    )

    pendientes_revision = c.execute(
        """
        SELECT DISTINCT u.id, u.full_name
        FROM approvals a
        JOIN users u ON u.id=a.usuario_id
        WHERE a.tramite_id=? AND a.aprobado=0 AND u.active=1
        ORDER BY u.id
        """,
        (tid,)
    ).fetchall()

    tipo_nombre = 'requerimiento' if t['tipo'] == 'REQ' else 'solicitud'
    for destinatario in pendientes_revision:
        c.execute(
            """
            INSERT INTO notificaciones(usuario_id, tramite_id, titulo, mensaje, tipo, leida, fecha)
            VALUES(?,?,?,?,?,0,?)
            """,
            (
                destinatario['id'], tid,
                f'{tipo_nombre.capitalize()} pendiente de revisión',
                f'{u["full_name"]} terminó su revisión del {tipo_nombre} {t["tracking"]}. Requiere su V.°B.°.',
                'accion', now
            )
        )

    c.commit()
    c.close()

    flash(
        'Su V.°B.° fue registrado y el trámite fue enviado al siguiente responsable.',
        'success'
    )

    return redirect(
        url_for('request_detail', tid=tid)
    )


@app.route('/requests/<int:tid>/delete', methods=['POST'])
@login_required
def delete_request(tid):
    u = user()
    obra = active_project()

    if not obra:
        abort(403)

    c = db()

    t = c.execute(
        """
        SELECT *
        FROM tramites
        WHERE id=?
        AND obra_id=?
        """,
        (tid, obra['id'])
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if (
        t['creador'] != u['id']
        or t['estado'] != 'Pendiente de mi revisión'
    ):
        c.close()
        abort(403)

    adjuntos = c.execute(
        """
        SELECT nombre_archivo
        FROM attachments
        WHERE tramite_id=?
        """,
        (tid,)
    ).fetchall()

    for adjunto in adjuntos:
        nombre_archivo = adjunto['nombre_archivo']
        if nombre_archivo:
            ruta = os.path.join(UPLOADS, nombre_archivo)
            if os.path.isfile(ruta):
                try:
                    os.remove(ruta)
                except OSError:
                    pass

    c.execute('DELETE FROM attachments WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM approvals WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM history WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM notificaciones WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM compra_detalle WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM regularizacion_compra_detalle WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM compras_logistica WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM items WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM gestion_logistica WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM gestion_sp WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM regularizaciones WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM archivos_logistica WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM solicitudes_tesoreria WHERE tramite_id=?', (tid,))
    c.execute('DELETE FROM tramites WHERE id=?', (tid,))

    c.commit()
    c.close()

    flash('El trámite fue eliminado.', 'success')
    return redirect(url_for('requests'))


@app.route('/requests/<int:tid>/edit', methods=['GET', 'POST'])
@login_required
def edit_request(tid):
    u = user()
    obra = active_project()

    if not obra:
        abort(403)

    c = db()

    t = c.execute(
        """
        SELECT *
        FROM tramites
        WHERE id=?
        AND obra_id=?
        """,
        (tid, obra['id'])
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    # Solo el creador puede modificar mientras todavía
    # está en su etapa de revisión.
    if (
        t['creador'] != u['id']
        or t['estado'] != 'Pendiente de mi revisión'
    ):
        c.close()
        abort(403)

    if request.method == 'POST':

        fecha = request.form.get('fecha', '').strip()
        numero = request.form.get('numero', '').strip()
        if t['tipo'] == 'REQ' and request.form.get('numero_correlativo'):
            numero_actual = (t['numero'] or '').strip()
            year_numero = numero_actual.split(
                '-')[-1] if '-' in numero_actual else (fecha[:4] if fecha else str(datetime.now().year))
            try:
                numero = compose_form_number(
                    'REQ', request.form.get('numero_correlativo'), year_numero)
            except ValueError as exc:
                c.close()
                flash(str(exc), 'warning')
                return redirect(url_for('edit_request', tid=tid))

        if not numero or not fecha:
            c.close()
            flash('Número y fecha son obligatorios.', 'danger')
            return redirect(
                url_for('edit_request', tid=tid)
            )

        # =====================================================
        # REQUERIMIENTO
        # =====================================================
        if t['tipo'] == 'REQ':

            duplicate = c.execute(
                """SELECT id FROM tramites WHERE obra_id=? AND tipo='REQ' AND LOWER(TRIM(numero))=LOWER(TRIM(?)) AND id<>? LIMIT 1""",
                (obra['id'], numero, tid)
            ).fetchone()
            if duplicate:
                c.close()
                flash(
                    f'El requerimiento N.° {numero} ya existe. Ingrese otro correlativo.', 'warning')
                return redirect(url_for('edit_request', tid=tid))

            secs = request.form.getlist('seccion[]')
            desc = request.form.getlist('descripcion[]')
            qty = request.form.getlist('cantidad[]')
            und = request.form.getlist('unidad[]')
            stock = request.form.getlist('stock[]')
            just = request.form.getlist('justificacion[]')
            prioridades = request.form.getlist('prioridad_item[]')
            fechas_req = request.form.getlist('fecha_requerida_item[]')
            item_keys = request.form.getlist('item_key[]')

            req_items = []

            for i, descripcion in enumerate(desc):

                descripcion = descripcion.strip()

                # Igual que al crear:
                # si la descripción está vacía, ignorar toda la fila.
                if not descripcion:
                    continue

                cantidad_txt = (
                    qty[i].strip()
                    if i < len(qty)
                    else ''
                )

                unidad = (
                    und[i].strip()
                    if i < len(und)
                    else ''
                )

                stock_txt = (
                    stock[i].strip()
                    if i < len(stock)
                    else ''
                )

                justificacion = (
                    just[i].strip()
                    if i < len(just)
                    else ''
                )

                seccion = (
                    secs[i]
                    if i < len(secs)
                    else ''
                )

                prioridad_item = prioridades[i].strip(
                ) if i < len(prioridades) else 'Normal'
                if prioridad_item not in ('Normal', 'Prioritario', 'Urgente'):
                    prioridad_item = 'Normal'
                fecha_requerida_item = fechas_req[i].strip(
                ) if i < len(fechas_req) else ''
                fecha_requerida_item = fecha_requerida_item or None
                item_key = item_keys[i].strip() if i < len(item_keys) else ''
                item_key = item_key or uuid.uuid4().hex

                if not cantidad_txt:
                    c.close()
                    flash(
                        'Todo material con descripción debe tener cantidad.',
                        'danger'
                    )
                    return redirect(
                        url_for('edit_request', tid=tid)
                    )

                if not unidad:
                    c.close()
                    flash(
                        'Todo material con descripción debe tener unidad.',
                        'danger'
                    )
                    return redirect(
                        url_for('edit_request', tid=tid)
                    )

                cantidad = parse_decimal(cantidad_txt)

                if cantidad <= 0:
                    c.close()
                    flash(
                        'La cantidad de cada material debe ser mayor que 0.',
                        'danger'
                    )
                    return redirect(
                        url_for('edit_request', tid=tid)
                    )

                stock_actual = (
                    parse_decimal(stock_txt)
                    if stock_txt
                    else 0
                )

                req_items.append(
                    (
                        seccion,
                        descripcion,
                        unidad,
                        cantidad,
                        stock_actual,
                        justificacion,
                        prioridad_item,
                        fecha_requerida_item,
                        item_key
                    )
                )

            if u['role'] in OFFICE_REQUIREMENT_ROLES:
                if any(item[0] != 'Útiles de oficina' for item in req_items):
                    c.close()
                    flash(
                        'Los requerimientos de Administración, Logística, Tesorería y Sistemas solo pueden usar Útiles de oficina.',
                        'danger'
                    )
                    return redirect(url_for('edit_request', tid=tid))

            if not req_items:
                c.close()

                flash(
                    'El requerimiento debe conservar al menos un material.',
                    'danger'
                )

                return redirect(
                    url_for('edit_request', tid=tid)
                )

            # Actualizar cabecera
            c.execute(
                """
                UPDATE tramites
                SET numero=?, fecha=?, formato=?, prioridad='Normal', fecha_requerida=NULL
                WHERE id=?
                """,
                (numero, fecha, 'F01A-LANR-' + (re.sub(r'\D', '',
                 numero.split('-')[0]) or numero.split('-')[0]), tid)
            )

            # Mantener imágenes solo de los ítems que continúan en el requerimiento.
            claves_actuales = {item[-1] for item in req_items}
            imagenes_previas = c.execute(
                'SELECT * FROM item_imagenes WHERE tramite_id=?', (tid,)
            ).fetchall()
            for img in imagenes_previas:
                if img['item_key'] not in claves_actuales:
                    ruta_img = os.path.join(UPLOADS, img['nombre_archivo'])
                    if os.path.exists(ruta_img):
                        try:
                            os.remove(ruta_img)
                        except OSError:
                            pass
                    c.execute('DELETE FROM item_imagenes WHERE id=?',
                              (img['id'],))

            # Reemplazar ítems
            c.execute(
                'DELETE FROM items WHERE tramite_id=?',
                (tid,)
            )

            nro_global = 0

            for (
                seccion,
                descripcion,
                unidad,
                cantidad,
                stock_actual,
                justificacion,
                prioridad_item,
                fecha_requerida_item,
                item_key
            ) in req_items:

                nro_global += 1

                c.execute(
                    """
                    INSERT INTO items(
                        tramite_id,
                        seccion,
                        nro,
                        descripcion,
                        unidad,
                        cantidad,
                        stock,
                        comprar,
                        justificacion,
                        prioridad,
                        fecha_requerida,
                        item_key
                    )
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
                    """,
                    (
                        tid,
                        seccion,
                        nro_global,
                        descripcion,
                        unidad,
                        cantidad,
                        stock_actual,
                        max(cantidad - stock_actual, 0),
                        justificacion,
                        prioridad_item,
                        fecha_requerida_item,
                        item_key
                    )
                )

                # Agregar nuevas imágenes seleccionadas para este ítem. Máximo 5 en total.
                nuevas_imagenes = [a for a in request.files.getlist(
                    f'imagenes_{item_key}') if a and a.filename]
                ids_a_eliminar = set()
                for img_id_txt in request.form.getlist('remove_image_ids[]'):
                    try:
                        ids_a_eliminar.add(int(img_id_txt))
                    except (TypeError, ValueError):
                        pass
                existentes_item = c.execute(
                    'SELECT id FROM item_imagenes WHERE tramite_id=? AND item_key=?',
                    (tid, item_key)
                ).fetchall()
                existentes_restantes = sum(
                    1 for img in existentes_item if img['id'] not in ids_a_eliminar)
                if existentes_restantes + len(nuevas_imagenes) > 5:
                    c.rollback()
                    c.close()
                    flash(
                        f'El ítem {nro_global:02d} admite como máximo 5 imágenes de referencia.', 'danger')
                    return redirect(url_for('edit_request', tid=tid))

                for archivo in nuevas_imagenes:
                    ext = os.path.splitext(archivo.filename)[1].lower()
                    if ext not in ('.jpg', '.jpeg', '.png', '.webp'):
                        continue
                    nombre_seguro = secure_filename(archivo.filename)
                    guardado = f'req_item_{tid}_{item_key}_{uuid.uuid4().hex[:10]}{ext}'
                    archivo.save(os.path.join(UPLOADS, guardado))
                    c.execute(
                        '''INSERT INTO item_imagenes(
                            tramite_id,item_key,nombre_original,nombre_archivo,creado
                        ) VALUES(?,?,?,?,?)''',
                        (tid, item_key, nombre_seguro, guardado,
                         datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                    )

            for img_id_txt in request.form.getlist('remove_image_ids[]'):
                try:
                    img_id = int(img_id_txt)
                except (TypeError, ValueError):
                    continue
                img = c.execute(
                    'SELECT * FROM item_imagenes WHERE id=? AND tramite_id=?',
                    (img_id, tid)
                ).fetchone()
                if img:
                    ruta_img = os.path.join(UPLOADS, img['nombre_archivo'])
                    if os.path.exists(ruta_img):
                        try:
                            os.remove(ruta_img)
                        except OSError:
                            pass
                    c.execute('DELETE FROM item_imagenes WHERE id=?', (img_id,))

            # -------------------------------------------------
            # Documentos generales del REQ (PDF / Excel)
            # -------------------------------------------------
            for aid_txt in request.form.getlist('eliminar_adjuntos[]'):
                try:
                    aid = int(aid_txt)
                except (TypeError, ValueError):
                    continue
                adjunto = c.execute(
                    'SELECT * FROM attachments WHERE id=? AND tramite_id=?',
                    (aid, tid)
                ).fetchone()
                if not adjunto:
                    continue
                ruta = os.path.join(UPLOADS, adjunto['nombre_archivo'])
                if os.path.isfile(ruta):
                    try:
                        os.remove(ruta)
                    except OSError:
                        pass
                c.execute(
                    'DELETE FROM attachments WHERE id=? AND tramite_id=?', (aid, tid))

            # Si se carga un archivo nuevo, reemplaza al archivo externo anterior.
            nuevos_req = [f for f in request.files.getlist(
                'documentos') if f and f.filename]
            if nuevos_req:
                archivo = nuevos_req[0]
                nombre_original = archivo.filename.strip()
                ext = os.path.splitext(nombre_original)[1].lower()
                if ext in ('.pdf', '.xls', '.xlsx', '.xlsm'):
                    anteriores = c.execute(
                        'SELECT * FROM attachments WHERE tramite_id=?', (tid,)
                    ).fetchall()
                    for anterior in anteriores:
                        ruta_anterior = os.path.join(
                            UPLOADS, anterior['nombre_archivo'])
                        if os.path.isfile(ruta_anterior):
                            try:
                                os.remove(ruta_anterior)
                            except OSError:
                                pass
                    c.execute(
                        'DELETE FROM attachments WHERE tramite_id=?', (tid,))
                    nombre_seguro = secure_filename(nombre_original)
                    nombre_guardado = (
                        f"{tid}_req_edit_{datetime.now().strftime('%Y%m%d%H%M%S%f')}_"
                        f"1_{nombre_seguro}"
                    )
                    archivo.save(os.path.join(UPLOADS, nombre_guardado))
                    c.execute(
                        '''INSERT INTO attachments(
                            tramite_id,nombre_original,nombre_archivo,orden,fecha
                        ) VALUES(?,?,?,?,?)''',
                        (tid, nombre_original, nombre_guardado, 1,
                         datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
                    )

        # =====================================================
        # SOLICITUD DE PAGO
        # =====================================================
        else:
            subtipo = request.form.get('subtipo', '').strip()
            beneficiario = request.form.get('beneficiario', '').strip()
            valid_types = ('Proveedor persona natural', 'Proveedor persona jurídica',
                           'Cuarta categoría / Recibo por Honorarios', 'Planillas')
            if subtipo not in valid_types or not beneficiario:
                c.close()
                flash('Tipo de solicitud y beneficiario son obligatorios.', 'danger')
                return redirect(url_for('edit_request', tid=tid))
            moneda = request.form.get('moneda', 'PEN')
            moneda = moneda if moneda in ('PEN', 'USD') else 'PEN'
            conceptos = request.form.getlist('concepto[]')
            unidades = request.form.getlist('unidad_sp[]')
            cantidades = request.form.getlist('cantidad_sp[]')
            costos = request.form.getlist('costo[]')
            despachos = request.form.getlist('nro_despacho[]')
            sp_items = []
            for i, concepto in enumerate(conceptos):
                concepto = concepto.strip()
                if not concepto:
                    continue
                unidad = unidades[i].strip() if i < len(unidades) else ''
                cantidad_txt = cantidades[i].strip(
                ) if i < len(cantidades) else ''
                costo_txt = costos[i].strip() if i < len(costos) else ''
                despacho = despachos[i].strip() if i < len(despachos) else ''
                if not unidad or cantidad_txt == '' or costo_txt == '':
                    c.close()
                    flash(
                        f'Complete unidad, cantidad y costo del ítem {i+1}.', 'danger')
                    return redirect(url_for('edit_request', tid=tid))
                cantidad = parse_decimal(cantidad_txt)
                costo = parse_decimal(costo_txt)
                if costo < 0:
                    c.close()
                    flash(
                        f'El costo del ítem {i+1} no puede ser negativo.', 'danger')
                    return redirect(url_for('edit_request', tid=tid))
                sp_items.append((concepto, unidad, cantidad,
                                costo, cantidad*costo, despacho))
            if not sp_items:
                c.close()
                flash('La solicitud debe conservar al menos un ítem.', 'danger')
                return redirect(url_for('edit_request', tid=tid))
            total = sum(x[4] for x in sp_items)
            usar_amort = request.form.get('usar_amortizacion') == '1'
            amort = parse_decimal(request.form.get(
                'amortizacion', '0')) if usar_amort else 0
            if amort < 0 or (total >= 0 and amort > total):
                c.close()
                flash('La amortización no es válida.', 'danger')
                return redirect(url_for('edit_request', tid=tid))
            abono = amort if usar_amort else total
            saldo = total-amort if usar_amort else 0
            celular = re.sub(r'\D', '', request.form.get('celular', ''))[:15]
            c.execute('''UPDATE tramites SET numero=?,fecha=?,subtipo=?,beneficiario=?,dni_ruc=?,responsable=?,celular=?,observaciones=?,moneda=?,fecha_limite_pago=?,amortizacion=?,saldo_pendiente=?,abono=? WHERE id=?''', (
                numero, fecha, subtipo, beneficiario, request.form.get('dni_ruc', '').strip(), request.form.get('responsable', '').strip(), celular, request.form.get('observaciones', '').strip(), moneda, request.form.get('fecha_limite_pago', '').strip() or None, amort, saldo, abono, tid))
            c.execute('DELETE FROM items WHERE tramite_id=?', (tid,))
            for nro, x in enumerate(sp_items, 1):
                c.execute(
                    'INSERT INTO items(tramite_id,nro,descripcion,unidad,cantidad,costo,monto,nro_despacho) VALUES(?,?,?,?,?,?,?,?)', (tid, nro, *x))
            c.execute('DELETE FROM sp_modalidades WHERE tramite_id=?', (tid,))
            mods = request.form.getlist('modalidad_pago[]')
            other = request.form.get('modalidad_otro', '').strip()
            if 'Otro' in mods and other:
                mods = [m for m in mods if m != 'Otro']+[other]
            for m in dict.fromkeys([m.strip() for m in mods if m.strip()]):
                c.execute(
                    'INSERT INTO sp_modalidades(tramite_id,modalidad) VALUES(?,?)', (tid, m))
            c.execute('DELETE FROM sp_cuentas WHERE tramite_id=?', (tid,))
            bancos = request.form.getlist('banco[]')
            cuentas = request.form.getlist('cuenta_cci[]')
            otros = request.form.getlist('banco_otro[]')
            for i, b in enumerate(bancos):
                b = b.strip()
                cuenta = cuentas[i].strip() if i < len(cuentas) else ''
                if b == 'Otro' and i < len(otros) and otros[i].strip():
                    b = otros[i].strip()
                if b:
                    c.execute(
                        'INSERT INTO sp_cuentas(tramite_id,banco,cuenta_cci) VALUES(?,?,?)', (tid, b, cuenta))
            # comprobantes existentes se conservan salvo que el usuario marque eliminar
            for cid_txt in request.form.getlist('eliminar_comprobantes[]'):
                try:
                    cid = int(cid_txt)
                except:
                    continue
                row = c.execute(
                    'SELECT * FROM sp_comprobantes WHERE id=? AND tramite_id=?', (cid, tid)).fetchone()
                if row:
                    if row['nombre_archivo']:
                        try:
                            os.remove(os.path.join(
                                UPLOADS, row['nombre_archivo']))
                        except OSError:
                            pass
                    c.execute('DELETE FROM sp_comprobantes WHERE id=?', (cid,))
            tipos = request.form.getlist('comprobante_tipo[]')
            nums = request.form.getlist('comprobante_numero[]')
            files = request.files.getlist('comprobante_archivo[]')
            start = c.execute(
                'SELECT COALESCE(MAX(orden),0) n FROM sp_comprobantes WHERE tramite_id=?', (tid,)).fetchone()['n']
            for i in range(max(len(tipos), len(nums), len(files))):
                tp = tipos[i].strip() if i < len(tipos) else ''
                num = nums[i].strip() if i < len(nums) else ''
                f = files[i] if i < len(files) else None
                if not tp and not num and not (f and f.filename):
                    continue
                orig = guard = ''
                if f and f.filename:
                    if not f.filename.lower().endswith(('.pdf', '.jpg', '.jpeg', '.png', '.webp')):
                        continue
                    orig = f.filename
                    guard = secure_filename(
                        f'SP_COMP_{tid}_{uuid.uuid4().hex[:8]}_{f.filename}')
                    f.save(os.path.join(UPLOADS, guard))
                start += 1
                c.execute('INSERT INTO sp_comprobantes(tramite_id,tipo,numero,nombre_original,nombre_archivo,orden) VALUES(?,?,?,?,?,?)',
                          (tid, tp, num, orig, guard, start))
            for aid_txt in request.form.getlist('eliminar_adjuntos[]'):
                try:
                    aid = int(aid_txt)
                except:
                    continue
                a = c.execute(
                    'SELECT * FROM attachments WHERE id=? AND tramite_id=?', (aid, tid)).fetchone()
                if a:
                    try:
                        os.remove(os.path.join(UPLOADS, a['nombre_archivo']))
                    except OSError:
                        pass
                    c.execute('DELETE FROM attachments WHERE id=?', (aid,))
            order = c.execute(
                'SELECT COALESCE(MAX(orden),0) n FROM attachments WHERE tramite_id=?', (tid,)).fetchone()['n']
            for f in request.files.getlist('documentos'):
                if not f or not f.filename or not f.filename.lower().endswith('.pdf'):
                    continue
                order += 1
                guard = secure_filename(
                    f'SP_DOC_{tid}_{order}_{uuid.uuid4().hex[:8]}_{f.filename}')
                f.save(os.path.join(UPLOADS, guard))
                c.execute('INSERT INTO attachments(tramite_id,nombre_original,nombre_archivo,orden,fecha) VALUES(?,?,?,?,?)',
                          (tid, f.filename, guard, order, datetime.now().strftime('%Y-%m-%d %H:%M:%S')))

        # =====================================================
        # HISTORIAL
        # =====================================================
        c.execute(
            """
            INSERT INTO history(
                tramite_id,
                usuario_id,
                accion,
                fecha
            )
            VALUES(?,?,?,?)
            """,
            (
                tid,
                u['id'],
                'Trámite modificado durante la revisión del creador.',
                datetime.now().strftime(
                    '%Y-%m-%d %H:%M:%S'
                )
            )
        )

        c.commit()
        c.close()

        flash(
            'Cambios guardados. Revise nuevamente antes de dar su V.°B.°.',
            'success'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=tid
            )
        )

    # =========================================================
    # GET - CARGAR EDICIÓN
    # =========================================================
    items = c.execute(
        """
        SELECT *
        FROM items
        WHERE tramite_id=?
        ORDER BY seccion,nro,id
        """,
        (tid,)
    ).fetchall()

    adjuntos = c.execute(
        """
        SELECT *
        FROM attachments
        WHERE tramite_id=?
        ORDER BY orden,id
        """,
        (tid,)
    ).fetchall()

    item_images = c.execute(
        "SELECT * FROM item_imagenes WHERE tramite_id=? ORDER BY id",
        (tid,)
    ).fetchall()
    images_by_key = {}
    for img in item_images:
        images_by_key.setdefault(img['item_key'], []).append(img)

    req_units, sp_units = get_active_units(c)
    sp_modalidades = c.execute('SELECT * FROM sp_modalidades WHERE tramite_id=? ORDER BY id',
                               (tid,)).fetchall() if t['tipo'] == 'SP' else []
    sp_cuentas = c.execute('SELECT * FROM sp_cuentas WHERE tramite_id=? ORDER BY id',
                           (tid,)).fetchall() if t['tipo'] == 'SP' else []
    sp_comprobantes = c.execute('SELECT * FROM sp_comprobantes WHERE tramite_id=? ORDER BY orden,id',
                                (tid,)).fetchall() if t['tipo'] == 'SP' else []
    c.close()

    return render_template(
        'edit_request.html',
        user=u,
        t=t,
        items=items,
        attachments=adjuntos,
        images_by_key=images_by_key,
        req_units=req_units,
        sp_units=sp_units,
        sp_modalidades=sp_modalidades, sp_cuentas=sp_cuentas, sp_comprobantes=sp_comprobantes
    )


def _can_user_view_tramite(c, t, u):
    if t['estado'] == 'Pendiente de mi revisión':
        return t['creador'] == u['id']

    if t['tipo'] == 'REQ' and t['estado'] == 'Pendiente de aprobación':
        if t['creador'] == u['id']:
            return True
        approval = c.execute(
            'SELECT 1 FROM approvals WHERE tramite_id=? AND usuario_id=? LIMIT 1',
            (t['id'], u['id'])
        ).fetchone()
        return approval is not None

    return True


@app.route('/requests')
@login_required
def requests():
    u = user()
    obra = active_project()
    if not obra:
        flash('No tienes una obra activa asignada.', 'danger')
        return redirect(url_for('home'))

    c = db()
    ts = c.execute(
        '''
        SELECT t.*, creador.full_name AS creador_nombre
        FROM tramites t
        JOIN users creador ON creador.id=t.creador
        WHERE t.obra_id=?
        ORDER BY t.id DESC
        ''',
        (obra['id'],)
    ).fetchall()
    ts = [t for t in ts if _can_user_view_tramite(c, t, u)]
    c.close()
    return render_template('requests.html', user=u, requests=ts)


@app.route('/requests/search')
@login_required
def search_request():
    u = user()
    if not active_project():
        abort(403)
    return render_template('search_request.html', user=u)


@app.route('/api/requests/search')
@login_required
def api_search_requests():
    u = user()

    obra = active_project()
    if not obra:
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
        AND t.obra_id = ?

        AND (
            t.tracking LIKE ?
            OR t.numero LIKE ?
        )

        ORDER BY t.id DESC

        LIMIT 15
        ''',
        (
            tipo,
            obra['id'],
            f'%{texto}%',
            f'%{texto}%'
        )
    ).fetchall()

    resultados = [r for r in resultados if _can_user_view_tramite(c, r, u)]
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


@app.route('/requests/<int:tid>/tracking')
@login_required
def request_tracking(tid):
    u = user()
    obra = active_project()
    if not obra:
        abort(404)

    c = db()
    t = c.execute(
        '''
        SELECT t.*, u.full_name AS creador_nombre
        FROM tramites t
        JOIN users u ON u.id=t.creador
        WHERE t.id=? AND t.obra_id=?
        ''',
        (tid, obra['id'])
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if not _can_user_view_tramite(c, t, u):
        c.close()
        abort(404)

    hs = c.execute(
        '''
        SELECT h.*, u.full_name AS nombre
        FROM history h
        JOIN users u ON u.id=h.usuario_id
        WHERE h.tramite_id=?
        ORDER BY h.id DESC
        ''',
        (tid,)
    ).fetchall()
    c.close()

    return render_template('request_tracking.html', user=u, t=t, history=hs)


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
        AND t.obra_id=?
        ''',
        (
            tid,
            active_project()['id']
        )
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    if not _can_user_view_tramite(c, t, u):
        c.close()
        abort(404)

    items = c.execute(
        '''
        SELECT *
        FROM items
        WHERE tramite_id=?
        ORDER BY seccion,nro
        ''',
        (tid,)
    ).fetchall()

    item_images = c.execute(
        '''SELECT * FROM item_imagenes WHERE tramite_id=? ORDER BY id''',
        (tid,)
    ).fetchall()
    images_by_key = {}
    for img in item_images:
        images_by_key.setdefault(img['item_key'], []).append(img)

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

    compras = c.execute(
        '''
        SELECT *
        FROM compras_logistica
        WHERE tramite_id=?
        ORDER BY id
        ''',
        (tid,)
    ).fetchall()

    compra_detalle = c.execute(
        '''
        SELECT
            cd.*,
            i.descripcion item_descripcion,
            i.unidad item_unidad,
            cl.nro_comprobante compra_comprobante
        FROM compra_detalle cd
        LEFT JOIN items i ON i.id=cd.item_id
        LEFT JOIN compras_logistica cl ON cl.id=cd.compra_id
        WHERE cd.tramite_id=?
        ORDER BY cd.id
        ''',
        (tid,)
    ).fetchall()

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

    archivos_pago_sp = c.execute(
        '''
        SELECT *
        FROM archivos_pago_sp
        WHERE tramite_id=?
        ORDER BY id ASC
        ''',
        (tid,)
    ).fetchall()

    sp_modalidades = c.execute('SELECT * FROM sp_modalidades WHERE tramite_id=? ORDER BY id',
                               (tid,)).fetchall() if t['tipo'] == 'SP' else []
    sp_cuentas = c.execute('SELECT * FROM sp_cuentas WHERE tramite_id=? ORDER BY id',
                           (tid,)).fetchall() if t['tipo'] == 'SP' else []
    sp_comprobantes = c.execute('SELECT * FROM sp_comprobantes WHERE tramite_id=? ORDER BY orden,id',
                                (tid,)).fetchall() if t['tipo'] == 'SP' else []
    sp_pagos = c.execute('''SELECT p.*,u.full_name pagador_nombre FROM sp_pagos_multiples p JOIN users u ON u.id=p.pagado_por WHERE p.tramite_id=? ORDER BY p.id''',
                         (tid,)).fetchall() if t['tipo'] == 'SP' else []
    fecha_hoy = datetime.now().strftime('%Y-%m-%d')

    c.close()

    return render_template(
        'request_detail.html',
        user=u,
        t=t,
        items=items,
        images_by_key=images_by_key,
        approvals=aps,
        history=hs,
        attachments=adjuntos,
        gestion=gestion,
        compras=compras,
        compra_detalle=compra_detalle,
        archivos_logistica=archivos_logistica,
        tesoreria=tesoreria,
        regularizaciones=regularizaciones,
        gestion_sp=gestion_sp,
        archivos_pago_sp=archivos_pago_sp,
        fecha_hoy=fecha_hoy,
        sp_modalidades=sp_modalidades, sp_cuentas=sp_cuentas, sp_comprobantes=sp_comprobantes, sp_pagos=sp_pagos
    )


@app.route('/request-item-images/<int:image_id>')
@login_required
def view_request_item_image(image_id):
    u = user()
    obra = active_project()
    if not obra:
        abort(403)
    c = db()
    img = c.execute(
        '''SELECT ii.* FROM item_imagenes ii
           JOIN tramites t ON t.id=ii.tramite_id
           WHERE ii.id=? AND t.obra_id=?''',
        (image_id, obra['id'])
    ).fetchone()
    c.close()
    if not img:
        abort(404)
    return send_from_directory(UPLOADS, img['nombre_archivo'])


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

    respuesta = send_from_directory(
        UPLOADS,
        adjunto['nombre_archivo'],
        as_attachment=False,
        download_name=adjunto['nombre_original']
    )

    # Conservar el nombre original cuando el navegador visualiza o descarga.
    nombre_original = adjunto['nombre_original'] or adjunto['nombre_archivo']
    nombre_fallback = secure_filename(nombre_original) or 'archivo'
    respuesta.headers['Content-Disposition'] = (
        f"inline; filename=\"{nombre_fallback}\"; filename*=UTF-8''{quote(nombre_original, safe='')}"
    )
    return respuesta


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

    respuesta = send_from_directory(
        UPLOADS,
        adjunto['nombre_archivo'],
        as_attachment=True,
        download_name=adjunto['nombre_original']
    )
    nombre_original = adjunto['nombre_original'] or adjunto['nombre_archivo']
    nombre_fallback = secure_filename(nombre_original) or 'archivo'
    respuesta.headers['Content-Disposition'] = (
        f"attachment; filename=\"{nombre_fallback}\"; filename*=UTF-8''{quote(nombre_original, safe='')}"
    )
    return respuesta


def _can_access_generated_pdf(tid, expected_type):
    u = user()
    obra = active_project()
    if not obra:
        abort(403)
    c = db()
    t = c.execute(
        'SELECT id, tipo, estado, creador FROM tramites WHERE id=? AND obra_id=?',
        (tid, obra['id'])
    ).fetchone()
    if not t or t['tipo'] != expected_type:
        c.close()
        abort(404)
    if not _can_user_view_tramite(c, t, u):
        c.close()
        abort(404)
    c.close()
    return t


@app.route('/requests/<int:tid>/pdf')
@login_required
def view_requirement_pdf(tid):

    _can_access_generated_pdf(tid, 'REQ')
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

    _can_access_generated_pdf(tid, 'REQ')
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


@app.route('/sp/comprobantes/<int:cid>')
@login_required
def view_sp_comprobante(cid):
    c = db()
    row = c.execute('''SELECT sc.* FROM sp_comprobantes sc JOIN tramites t ON t.id=sc.tramite_id WHERE sc.id=? AND t.obra_id=?''',
                    (cid, active_project()['id'])).fetchone()
    c.close()
    if not row or not row['nombre_archivo']:
        abort(404)
    return send_from_directory(UPLOADS, row['nombre_archivo'], as_attachment=False, download_name=row['nombre_original'])


@app.route('/sp/payments/<int:pid>/evidence')
@login_required
def view_sp_multiple_payment(pid):
    c = db()
    row = c.execute('''SELECT p.* FROM sp_pagos_multiples p JOIN tramites t ON t.id=p.tramite_id WHERE p.id=? AND t.obra_id=?''',
                    (pid, active_project()['id'])).fetchone()
    c.close()
    if not row:
        abort(404)
    return send_from_directory(UPLOADS, row['nombre_archivo'], as_attachment=False, download_name=row['nombre_original'])


@app.route('/approvals/<int:aid>/approve', methods=['POST'])
@login_required
def approve(aid):
    u = user()
    c = db()

    a = c.execute(
        'SELECT * FROM approvals WHERE id=?',
        (aid,)
    ).fetchone()

    if not a or a['usuario_id'] != u['id']:
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
                SET estado='Pendiente de Administración'
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
            SELECT id, role
            FROM users
            WHERE role IN ('Logística','Administración','Gerencia General','Tesorería') AND active=1
            '''
        ).fetchall()

        for destinatario in destinatarios:

            rol_destino = destinatario['role']

            if rol_destino == 'Logística':
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
        sara = c.execute(
            '''SELECT u.id FROM users u JOIN usuario_obra uo ON uo.usuario_id=u.id WHERE u.role='Administración' AND u.active=1 AND uo.obra_id=? AND uo.active=1 ORDER BY u.id LIMIT 1''',
            (t['obra_id'],)
        ).fetchone()
        if sara:
            notificaciones_pendientes.append(
                (
                    sara['id'], tid,
                    'Solicitud pendiente de Administración',
                    f"La solicitud {t['tracking']} completó el V°B° de obra requerido. Corresponde continuar con la revisión de Administración.",
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
    if u['role'] != 'Administración':
        abort(403)
    asignado = request.form.get('asignado_pago', '').strip()
    if asignado not in ('Tesorería', 'Gerencia General'):
        flash('Seleccione quién realizará el pago.', 'danger')
        return redirect(url_for('request_detail', tid=tid))
    c = db()
    t = c.execute(
        "SELECT * FROM tramites WHERE id=? AND tipo='SP'", (tid,)).fetchone()
    if not t:
        c.close()
        abort(404)
    if t['estado'] != 'Pendiente de Administración':
        c.close()
        flash('Esta solicitud no está pendiente de Administración.', 'warning')
        return redirect(url_for('request_detail', tid=tid))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute('INSERT OR IGNORE INTO gestion_sp(tramite_id) VALUES(?)', (tid,))
    c.execute('UPDATE gestion_sp SET asignado_pago=?,asignado_por=?,fecha_asignacion=? WHERE tramite_id=?',
              (asignado, u['id'], now, tid))
    estado = 'Asignada a Tesorería' if asignado == 'Tesorería' else 'Asignada a Gerencia General'
    c.execute('UPDATE tramites SET estado=? WHERE id=?', (estado, tid))
    c.execute('INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)',
              (tid, u['id'], f'Administración revisó y asignó el pago a {asignado}', now))
    destino_role = 'Tesorería' if asignado == 'Tesorería' else 'Gerencia General'
    destinos = c.execute('''SELECT u.id FROM users u JOIN usuario_obra uo ON uo.usuario_id=u.id WHERE u.role=? AND u.active=1 AND uo.obra_id=? AND uo.active=1''',
                         (destino_role, t['obra_id'])).fetchall()
    c.commit()
    c.close()
    for d in destinos:
        notify(d['id'], tid, 'Solicitud asignada para pago',
               f'La solicitud {t["tracking"]} fue asignada a {asignado}.', 'accion')
    flash('Solicitud revisada y responsable de pago asignado.', 'success')
    return redirect(url_for('request_detail', tid=tid))


@app.route('/sp/<int:tid>/register-payment', methods=['POST'])
@login_required
def register_sp_payment(tid):
    u = user()
    c = db()
    t = c.execute(
        "SELECT * FROM tramites WHERE id=? AND tipo='SP'", (tid,)).fetchone()
    g = c.execute('SELECT * FROM gestion_sp WHERE tramite_id=?',
                  (tid,)).fetchone()
    if not t or not g:
        c.close()
        abort(404)
    expected = 'Tesorería' if g['asignado_pago'] == 'Tesorería' else 'Gerencia General'
    if u['role'] != expected:
        c.close()
        abort(403)
    if t['estado'] not in ('Asignada a Tesorería', 'Asignada a Gerencia General', 'Pago parcial'):
        c.close()
        flash('La solicitud no está habilitada para registrar pagos.', 'warning')
        return redirect(url_for('request_detail', tid=tid))
    medio = request.form.get('medio_pago', '').strip()
    banco = request.form.get('banco_pago', '').strip()
    nro = request.form.get('nro_operacion', '').strip()
    fecha = request.form.get('fecha_pago', '').strip()
    try:
        monto = parse_decimal(request.form.get('monto_pagado', '0'))
    except:
        monto = 0
    evidencia = request.files.get('evidencia_pago')
    if not medio or not fecha or monto <= 0 or not evidencia or not evidencia.filename:
        c.close()
        flash('Medio, fecha, monto y evidencia de pago son obligatorios.', 'danger')
        return redirect(url_for('request_detail', tid=tid))
    pagado = float(c.execute(
        'SELECT COALESCE(SUM(monto),0) v FROM sp_pagos_multiples WHERE tramite_id=?', (tid,)).fetchone()['v'])
    objetivo = float(t['abono'] or 0)
    restante = max(0, objetivo-pagado)
    if monto > restante+0.01:
        c.close()
        flash(
            f'El monto supera el saldo por pagar ({restante:,.2f}).', 'danger')
        return redirect(url_for('request_detail', tid=tid))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    orig = evidencia.filename
    guard = secure_filename(f'SP_PAGO_{tid}_{uuid.uuid4().hex[:10]}_{orig}')
    evidencia.save(os.path.join(UPLOADS, guard))
    c.execute('''INSERT INTO sp_pagos_multiples(tramite_id,pagado_por,medio_pago,banco_pago,nro_operacion,monto,fecha_pago,nombre_original,nombre_archivo,creado) VALUES(?,?,?,?,?,?,?,?,?,?)''',
              (tid, u['id'], medio, banco, nro, monto, fecha, orig, guard, now))
    nuevo = pagado+monto
    final = nuevo+0.01 >= objetivo
    estado = 'Pagada pendiente conformidad GG' if final else 'Pago parcial'
    c.execute('UPDATE tramites SET estado=? WHERE id=?', (estado, tid))
    c.execute('UPDATE gestion_sp SET pagado_por=?,medio_pago=?,banco_pago=?,nro_operacion=?,monto_pagado=?,fecha_pago=?,nombre_original_pago=?,nombre_archivo_pago=? WHERE tramite_id=?',
              (u['id'], medio, banco, nro, nuevo, fecha, orig, guard, tid))
    c.execute('INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)',
              (tid, u['id'], f'Pago registrado: {monto:,.2f}. Acumulado: {nuevo:,.2f}', now))
    c.commit()
    c.close()
    flash('Pago registrado correctamente.' if final else 'Pago parcial registrado. La solicitud mantiene saldo pendiente.', 'success')
    return redirect(url_for('request_detail', tid=tid))


@app.route('/sp/<int:tid>/final-approval', methods=['POST'])
@login_required
def confirm_sp_payment(tid):

    u = user()

    if u['role'] != 'Gerencia General':
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
        WHERE role IN ('Control y Planeamiento','Gerencia de Obra')
          AND active=1
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


@app.route('/sp/payment-files/<int:aid>/view')
@login_required
def view_sp_payment_file(aid):

    c = db()

    archivo = c.execute(
        '''
        SELECT *
        FROM archivos_pago_sp
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
        as_attachment=False
    )


@app.route('/sp/payment-files/<int:aid>/download')
@login_required
def download_sp_payment_file(aid):

    c = db()

    archivo = c.execute(
        '''
        SELECT *
        FROM archivos_pago_sp
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


@app.route('/requests/<int:tid>/status', methods=['POST'])
@login_required
def update_request_status(tid):

    u = user()
    est = request.form.get('estado', '')

    # Aquí únicamente se confirma la recepción final en obra
    if est != 'Recibido en obra':
        abort(403)

    if u['role'] not in (
        'Gerencia de Obra',
        'Control y Planeamiento'
    ):
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
        WHERE role IN ('Gerencia General','Administración','Logística','Tesorería') AND active=1
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


@app.route('/profile', methods=['GET', 'POST'])
@login_required
def profile():

    u = user()

    if request.method == 'POST':

        full_name = request.form.get(
            'full_name',
            ''
        ).strip()

        email = request.form.get(
            'email',
            ''
        ).strip().lower()

        phone = request.form.get(
            'phone',
            ''
        ).strip()

        if phone and not re.fullmatch(
            r'\d{7,15}',
            phone
        ):
            flash(
                'El teléfono debe contener únicamente entre 7 y 15 números.',
                'danger'
            )

            return redirect(
                url_for('profile')
            )

        if not full_name:
            flash(
                'Debe ingresar sus nombres y apellidos.',
                'danger'
            )

            return redirect(
                url_for('profile')
            )

        # ==========================================
        # VALIDAR CORREO
        # ==========================================

        if email:

            patron_email = (
                r'^[A-Za-z0-9._%+-]+'
                r'@[A-Za-z0-9.-]+'
                r'\.[A-Za-z]{2,}$'
            )

            if not re.match(
                patron_email,
                email
            ):

                flash(
                    'Ingrese un correo electrónico válido.',
                    'danger'
                )

                return redirect(
                    url_for('profile')
                )

        c = db()

        # Evitar que dos usuarios tengan el mismo correo
        if email:

            correo_existente = c.execute(
                '''
                SELECT id
                FROM users
                WHERE LOWER(email)=?
                AND id!=?
                ''',
                (
                    email,
                    u['id']
                )
            ).fetchone()

            if correo_existente:

                c.close()

                flash(
                    'Ese correo electrónico ya está registrado en otra cuenta.',
                    'danger'
                )

                return redirect(
                    url_for('profile')
                )

        # ==========================================
        # FOTO DE PERFIL
        # ==========================================

        profile_photo = u['profile_photo']

        remove_photo = (
            request.form.get('remove_photo') == '1'
        )

        if remove_photo and profile_photo:

            carpeta_fotos = os.path.join(
                BASE,
                'static',
                'profile_photos'
            )

            ruta_anterior = os.path.join(
                carpeta_fotos,
                profile_photo
            )

            if os.path.exists(ruta_anterior):

                try:
                    os.remove(ruta_anterior)

                except OSError:
                    pass

            profile_photo = None

        foto = request.files.get(
            'profile_photo'
        )

        if foto and foto.filename:

            extensiones_permitidas = {
                '.jpg',
                '.jpeg',
                '.png',
                '.webp'
            }

            extension = os.path.splitext(
                foto.filename
            )[1].lower()

            if extension not in extensiones_permitidas:

                c.close()

                flash(
                    'La foto debe ser JPG, JPEG, PNG o WEBP.',
                    'danger'
                )

                return redirect(
                    url_for('profile')
                )

            carpeta_fotos = os.path.join(
                BASE,
                'static',
                'profile_photos'
            )

            os.makedirs(
                carpeta_fotos,
                exist_ok=True
            )

            nombre_foto = (
                f'user_{u["id"]}_'
                f'{datetime.now().strftime("%Y%m%d%H%M%S")}'
                f'{extension}'
            )

            foto.save(
                os.path.join(
                    carpeta_fotos,
                    nombre_foto
                )
            )

            # Eliminar foto anterior
            if profile_photo:

                ruta_anterior = os.path.join(
                    carpeta_fotos,
                    profile_photo
                )

                if os.path.exists(
                    ruta_anterior
                ):

                    try:
                        os.remove(
                            ruta_anterior
                        )

                    except OSError:
                        pass

            profile_photo = nombre_foto

        # ==========================================
        # ACTUALIZAR DATOS DEL PERFIL
        # ==========================================

        c.execute(
            '''
            UPDATE users
            SET full_name=?,
                email=?,
                phone=?,
                profile_photo=?,
                updated_at=?
            WHERE id=?
            ''',
            (
                full_name,
                email,
                phone,
                profile_photo,
                datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                u['id']
            )
        )

        c.commit()
        c.close()

        flash(
            'Perfil actualizado correctamente.',
            'success'
        )

        return redirect(
            url_for('profile')
        )

    return render_template(
        'profile.html',
        user=u
    )


@app.route('/units')
@login_required
def units():
    u = user()
    if u['role'] != 'Sistemas':
        abort(403)

    c = db()
    unidades = c.execute(
        '''
        SELECT *
        FROM unidades_catalogo
        ORDER BY active DESC, abreviatura COLLATE NOCASE, uso
        '''
    ).fetchall()
    c.close()

    return render_template('units.html', user=u, unidades=unidades)


@app.route('/units/create', methods=['POST'])
@login_required
def create_unit():
    u = user()
    if u['role'] != 'Sistemas':
        abort(403)

    abreviatura = request.form.get('abreviatura', '').strip().upper()
    uso = request.form.get('uso', '').strip().upper()

    if not abreviatura:
        flash('Escriba la abreviatura de la unidad.', 'danger')
        return redirect(url_for('units'))

    if len(abreviatura) > 20:
        flash('La abreviatura no puede superar 20 caracteres.', 'danger')
        return redirect(url_for('units'))

    if uso not in ('REQ', 'SP', 'AMBOS'):
        flash('Seleccione dónde se utilizará la unidad.', 'danger')
        return redirect(url_for('units'))

    c = db()
    existing = c.execute(
        '''SELECT id FROM unidades_catalogo
           WHERE abreviatura=? COLLATE NOCASE AND uso=?''',
        (abreviatura, uso)
    ).fetchone()

    if existing:
        c.close()
        flash('Esa unidad ya existe para el uso seleccionado.', 'warning')
        return redirect(url_for('units'))

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute(
        '''INSERT INTO unidades_catalogo(abreviatura, uso, active, created_at, updated_at)
           VALUES(?,?,1,?,?)''',
        (abreviatura, uso, now, now)
    )
    c.commit()
    c.close()
    flash(f'Unidad {abreviatura} agregada correctamente.', 'success')
    return redirect(url_for('units'))


@app.route('/units/<int:unit_id>/edit', methods=['POST'])
@login_required
def edit_unit(unit_id):
    u = user()
    if u['role'] != 'Sistemas':
        abort(403)

    abreviatura = request.form.get('abreviatura', '').strip().upper()
    uso = request.form.get('uso', '').strip().upper()

    if not abreviatura or len(abreviatura) > 20:
        flash('Ingrese una abreviatura válida de hasta 20 caracteres.', 'danger')
        return redirect(url_for('units'))
    if uso not in ('REQ', 'SP', 'AMBOS'):
        flash('Seleccione un uso válido.', 'danger')
        return redirect(url_for('units'))

    c = db()
    target = c.execute(
        'SELECT * FROM unidades_catalogo WHERE id=?', (unit_id,)).fetchone()
    if not target:
        c.close()
        abort(404)

    duplicate = c.execute(
        '''SELECT id FROM unidades_catalogo
           WHERE abreviatura=? COLLATE NOCASE AND uso=? AND id!=?''',
        (abreviatura, uso, unit_id)
    ).fetchone()
    if duplicate:
        c.close()
        flash('Ya existe otra unidad con esa abreviatura y uso.', 'warning')
        return redirect(url_for('units'))

    c.execute(
        '''UPDATE unidades_catalogo
           SET abreviatura=?, uso=?, updated_at=?
           WHERE id=?''',
        (abreviatura, uso, datetime.now().strftime('%Y-%m-%d %H:%M:%S'), unit_id)
    )
    c.commit()
    c.close()
    flash('Unidad actualizada correctamente.', 'success')
    return redirect(url_for('units'))


@app.route('/units/<int:unit_id>/status', methods=['POST'])
@login_required
def toggle_unit_status(unit_id):
    u = user()
    if u['role'] != 'Sistemas':
        abort(403)

    c = db()
    row = c.execute('SELECT * FROM unidades_catalogo WHERE id=?',
                    (unit_id,)).fetchone()
    if not row:
        c.close()
        abort(404)

    new_status = 0 if row['active'] else 1
    c.execute(
        'UPDATE unidades_catalogo SET active=?, updated_at=? WHERE id=?',
        (new_status, datetime.now().strftime('%Y-%m-%d %H:%M:%S'), unit_id)
    )
    c.commit()
    c.close()
    flash('Unidad activada.' if new_status else 'Unidad desactivada.', 'success')
    return redirect(url_for('units'))


@app.route('/units/<int:unit_id>/delete', methods=['POST'])
@login_required
def delete_unit(unit_id):
    u = user()
    if u['role'] != 'Sistemas':
        abort(403)

    c = db()
    row = c.execute('SELECT * FROM unidades_catalogo WHERE id=?',
                    (unit_id,)).fetchone()
    if not row:
        c.close()
        abort(404)

    used = c.execute(
        'SELECT COUNT(*) AS n FROM items WHERE UPPER(TRIM(unidad))=UPPER(TRIM(?))',
        (row['abreviatura'],)
    ).fetchone()['n']

    if used:
        c.execute(
            'UPDATE unidades_catalogo SET active=0, updated_at=? WHERE id=?',
            (datetime.now().strftime('%Y-%m-%d %H:%M:%S'), unit_id)
        )
        c.commit()
        c.close()
        flash('La unidad ya fue usada en trámites; se desactivó para conservar el historial.', 'warning')
        return redirect(url_for('units'))

    c.execute('DELETE FROM unidades_catalogo WHERE id=?', (unit_id,))
    c.commit()
    c.close()
    flash('Unidad eliminada correctamente.', 'success')
    return redirect(url_for('units'))


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

    # Solo Sistemas administra accesos
    if u['role'] != 'Sistemas':
        abort(403)

    username = request.form.get(
        'username',
        ''
    ).strip()

    role = request.form.get(
        'role',
        ''
    ).strip()

    # ==========================================
    # VALIDAR USUARIO
    # ==========================================

    if not username:

        flash(
            'El nombre de usuario es obligatorio.',
            'danger'
        )

        return redirect(
            url_for('users')
        )

    # Solo letras, números, punto,
    # guion y guion bajo
    if not re.fullmatch(
        r'[A-Za-z0-9._-]{3,30}',
        username
    ):

        flash(
            'El usuario debe tener entre 3 y 30 caracteres y solo puede contener letras, números, punto, guion o guion bajo.',
            'danger'
        )

        return redirect(
            url_for('users')
        )

    # ==========================================
    # VALIDAR ROL
    # ==========================================

    allowed_roles = (
        'Gerencia de Obra',
        'Control y Planeamiento',
        'Gerencia General',
        'Administración',
        'Logística',
        'Tesorería',
        'Contabilidad',
        'Sistemas'
    )

    if role not in allowed_roles:
        abort(400)

    c = db()

    # ==========================================
    # COMPROBAR QUE EL USUARIO EXISTE
    # ==========================================

    target = c.execute(
        '''
        SELECT *
        FROM users
        WHERE id=?
        ''',
        (uid,)
    ).fetchone()

    if not target:
        c.close()
        abort(404)

    # ==========================================
    # EVITAR USUARIOS DUPLICADOS
    # ==========================================

    existing = c.execute(
        '''
        SELECT id
        FROM users
        WHERE username=? COLLATE NOCASE
          AND id!=?
        ''',
        (
            username,
            uid
        )
    ).fetchone()

    if existing:

        c.close()

        flash(
            'Ese nombre de usuario ya está en uso.',
            'danger'
        )

        return redirect(
            url_for('users')
        )

    # ==========================================
    # ACTUALIZAR SOLO DATOS DE ACCESO
    # ==========================================

    c.execute(
        '''
        UPDATE users
        SET username=?,
            role=?,
            updated_at=?
        WHERE id=?
        ''',
        (
            username,
            role,
            datetime.now().strftime(
                '%Y-%m-%d %H:%M:%S'
            ),
            uid
        )
    )

    c.commit()
    c.close()

    flash(
        'Acceso del usuario actualizado correctamente.',
        'success'
    )

    return redirect(
        url_for('users')
    )


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
        '''
        UPDATE users
        SET active=?,
            updated_at=?
        WHERE id=?
        ''',
        (
            new_request_estado,
            datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            uid
        )
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

    # Solo Sistemas puede asignar contraseñas
    if u['role'] != 'Sistemas':
        abort(403)

    nueva = request.form.get(
        'nueva_password',
        ''
    )

    confirmar = request.form.get(
        'confirmar_password',
        ''
    )

    # ==========================================
    # VALIDAR POLÍTICA DE CONTRASEÑA
    # ==========================================

    valida, mensaje = validate_password(
        nueva
    )

    if not valida:

        flash(
            mensaje,
            'danger'
        )

        return redirect(
            url_for('users')
        )

    # ==========================================
    # CONFIRMACIÓN
    # ==========================================

    if nueva != confirmar:

        flash(
            'Las contraseñas no coinciden.',
            'danger'
        )

        return redirect(
            url_for('users')
        )

    # ==========================================
    # BUSCAR USUARIO
    # ==========================================

    c = db()

    usuario_objetivo = c.execute(
        '''
        SELECT id, password_hash
        FROM users
        WHERE id=?
        ''',
        (uid,)
    ).fetchone()

    if not usuario_objetivo:

        c.close()
        abort(404)

    # ==========================================
    # EVITAR REPETIR CONTRASEÑA ACTUAL
    # ==========================================

    if check_password_hash(
        usuario_objetivo['password_hash'],
        nueva
    ):

        c.close()

        flash(
            'La nueva contraseña debe ser diferente a la contraseña actual del usuario.',
            'danger'
        )

        return redirect(
            url_for('users')
        )

    # ==========================================
    # GUARDAR
    # ==========================================

    c.execute(
        '''
        UPDATE users
        SET password_hash=?,
            updated_at=?
        WHERE id=?
        ''',
        (
            generate_password_hash(nueva),
            datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            uid
        )
    )

    c.commit()
    c.close()

    flash(
        'Contraseña asignada correctamente.',
        'success'
    )

    return redirect(
        url_for('users')
    )


@app.route('/logistics/<int:tid>/receive', methods=['POST'])
@login_required
def logistics_receive(tid):
    u = user()

    if u['role'] != 'Logística':
        abort(403)

    c = db()

    t = c.execute(
        'SELECT * FROM tramites WHERE id=?',
        (tid,)
    ).fetchone()

    if not t or t['tipo'] != 'REQ' or t['estado'] not in ('Aprobado', 'Pendiente de cotización'):
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
        SET estado='Pendiente de cotización'
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
            'Requerimiento recibido por Logística - pendiente de cotización',
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


@app.route('/logistics/<int:tid>/quotations')
@login_required
def logistics_quotations(tid):
    u = user()
    obra = active_project()
    c = db()
    t = c.execute("SELECT * FROM tramites WHERE id=? AND obra_id=?",
                  (tid, obra['id'])).fetchone()
    if not t or t['tipo'] != 'REQ':
        c.close()
        abort(404)
    if u['role'] not in ('Logística', 'Administración', 'Sistemas'):
        c.close()
        abort(403)
    items = c.execute("""SELECT * FROM items WHERE tramite_id=?
        ORDER BY CASE COALESCE(prioridad,'Normal') WHEN 'Urgente' THEN 0 WHEN 'Prioritario' THEN 1 ELSE 2 END,
                 CASE WHEN fecha_requerida IS NULL OR fecha_requerida='' THEN 1 ELSE 0 END,
                 fecha_requerida ASC, seccion, nro""", (tid,)).fetchall()
    proveedores = c.execute(
        "SELECT * FROM proveedores WHERE active=1 ORDER BY nombre").fetchall()
    cotizaciones = c.execute("""SELECT q.*,p.nombre proveedor_nombre,p.documento proveedor_documento,u.full_name creador_nombre,
        (SELECT COUNT(*) FROM cotizacion_items qi WHERE qi.cotizacion_id=q.id) item_count,
        (SELECT COALESCE(SUM(qi.cantidad*qi.precio_unitario),0) FROM cotizacion_items qi WHERE qi.cotizacion_id=q.id) total
        FROM cotizaciones q JOIN proveedores p ON p.id=q.proveedor_id LEFT JOIN users u ON u.id=q.creado_por
        WHERE q.tramite_id=? ORDER BY q.id DESC""", (tid,)).fetchall()
    qitems = c.execute("""SELECT qi.*,i.descripcion,i.unidad,p.nombre proveedor_nombre,q.estado,q.tipo_sustento,q.id cotizacion_id
        FROM cotizacion_items qi JOIN items i ON i.id=qi.item_id JOIN cotizaciones q ON q.id=qi.cotizacion_id JOIN proveedores p ON p.id=q.proveedor_id
        WHERE q.tramite_id=? ORDER BY i.nro,q.id""", (tid,)).fetchall()
    qfiles = c.execute(
        """SELECT f.* FROM cotizacion_archivos f JOIN cotizaciones q ON q.id=f.cotizacion_id WHERE q.tramite_id=? ORDER BY f.id""", (tid,)).fetchall()
    autorizaciones = c.execute("""SELECT a.*,u.full_name autorizado_nombre,
        (SELECT COALESCE(SUM(ai.subtotal),0) FROM autorizacion_items ai WHERE ai.autorizacion_id=a.id) total
        FROM autorizaciones_compra a LEFT JOIN users u ON u.id=a.autorizado_por WHERE a.tramite_id=? ORDER BY a.id DESC""", (tid,)).fetchall()
    aitems = c.execute("""SELECT ai.*,i.descripcion,i.unidad,p.nombre proveedor_nombre FROM autorizacion_items ai
        JOIN items i ON i.id=ai.item_id JOIN proveedores p ON p.id=ai.proveedor_id JOIN autorizaciones_compra a ON a.id=ai.autorizacion_id
        WHERE a.tramite_id=? ORDER BY ai.autorizacion_id,i.nro""", (tid,)).fetchall()
    pagos = c.execute(
        "SELECT * FROM solicitudes_tesoreria WHERE tramite_id=? AND origen='REQ-Cotizacion' ORDER BY id DESC", (tid,)).fetchall()
    # cantidades ya autorizadas válidas por item
    auth_rows = c.execute("""SELECT ai.item_id,COALESCE(SUM(ai.cantidad),0) qty FROM autorizacion_items ai JOIN autorizaciones_compra a ON a.id=ai.autorizacion_id
        WHERE a.tramite_id=? AND a.estado='Autorizada' GROUP BY ai.item_id""", (tid,)).fetchall()
    autorizado = {r['item_id']: float(r['qty'] or 0) for r in auth_rows}
    c.close()
    return render_template('quotation_management.html', user=u, t=t, items=items, proveedores=proveedores, cotizaciones=cotizaciones,
                           qitems=qitems, qfiles=qfiles, autorizaciones=autorizaciones, aitems=aitems, pagos=pagos, autorizado=autorizado, fecha_hoy=datetime.now().strftime('%Y-%m-%d'))


@app.route('/providers/create', methods=['POST'])
@login_required
def provider_create():
    u = user()
    if u['role'] not in ('Logística', 'Administración', 'Sistemas'):
        abort(403)
    tid = int(request.form.get('tid') or 0)
    nombre = request.form.get('nombre', '').strip()
    documento = request.form.get('documento', '').strip()
    if not nombre:
        flash('Ingrese el nombre o razón social del proveedor.', 'danger')
        return redirect(url_for('logistics_quotations', tid=tid))
    c = db()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    existe = c.execute(
        "SELECT id FROM proveedores WHERE LOWER(nombre)=LOWER(?)", (nombre,)).fetchone()
    if existe:
        flash('Ese proveedor ya está registrado.', 'warning')
    else:
        c.execute("INSERT INTO proveedores(nombre,documento,creado,actualizado) VALUES(?,?,?,?)",
                  (nombre, documento or None, now, now))
        c.commit()
        flash('Proveedor registrado.', 'success')
    c.close()
    return redirect(url_for('logistics_quotations', tid=tid))


@app.route('/logistics/<int:tid>/quotations/create', methods=['POST'])
@login_required
def quotation_create(tid):
    u = user()
    if u['role'] != 'Logística':
        abort(403)
    c = db()
    t = c.execute("SELECT * FROM tramites WHERE id=?", (tid,)).fetchone()
    if not t or t['tipo'] != 'REQ' or t['estado'] not in ('Recibido por Logística', 'En gestión de compra', 'Pendiente de cotización', 'Cotizaciones en gestión'):
        c.close()
        abort(400)
    proveedor_id = request.form.get('proveedor_id', '').strip()
    tipo = request.form.get('tipo_sustento', 'Cotización').strip()
    fecha = request.form.get('fecha', '').strip(
    ) or datetime.now().strftime('%Y-%m-%d')
    obs = request.form.get('observacion', '').strip()
    if tipo not in ('Cotización', 'Proveedor habitual'):
        c.close()
        abort(400)
    if not proveedor_id:
        c.close()
        flash('Seleccione un proveedor.', 'danger')
        return redirect(url_for('logistics_quotations', tid=tid))
    item_ids = request.form.getlist('item_id[]')
    cantidades = request.form.getlist('cantidad[]')
    precios = request.form.getlist('precio_unitario[]')
    filas = []
    for idx, item_id in enumerate(item_ids):
        qty = parse_decimal(cantidades[idx] if idx < len(cantidades) else '')
        price = parse_decimal(precios[idx] if idx < len(precios) else '')
        if qty > 0 or price > 0:
            if qty <= 0 or price <= 0:
                c.close()
                flash(
                    'Cada producto seleccionado debe tener cantidad y precio unitario mayores que 0.', 'danger')
                return redirect(url_for('logistics_quotations', tid=tid))
            filas.append((int(item_id), qty, price))
    if not filas:
        c.close()
        flash('Seleccione al menos un producto con cantidad y precio.', 'danger')
        return redirect(url_for('logistics_quotations', tid=tid))
    archivos = [f for f in request.files.getlist(
        'archivos') if f and f.filename]
    if tipo == 'Cotización' and not archivos:
        c.close()
        flash('Adjunte el PDF o imagen de la cotización.', 'danger')
        return redirect(url_for('logistics_quotations', tid=tid))
    permitidos = ('.pdf', '.png', '.jpg', '.jpeg', '.webp')
    if any(not f.filename.lower().endswith(permitidos) for f in archivos):
        c.close()
        flash('Los archivos deben ser PDF o imagen.', 'danger')
        return redirect(url_for('logistics_quotations', tid=tid))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cur = c.execute("INSERT INTO cotizaciones(tramite_id,proveedor_id,tipo_sustento,fecha,estado,observacion,creado_por,creado) VALUES(?,?,?,?,?,?,?,?)",
                    (tid, int(proveedor_id), tipo, fecha, 'Borrador', obs, u['id'], now))
    qid = cur.lastrowid
    for item_id, qty, price in filas:
        c.execute("INSERT INTO cotizacion_items(cotizacion_id,item_id,cantidad,precio_unitario) VALUES(?,?,?,?)",
                  (qid, item_id, qty, price))
    for idx, f in enumerate(archivos, 1):
        original = f.filename
        guardado = secure_filename(
            f'COT_{tid}_{qid}_{idx}_{datetime.now().strftime("%Y%m%d%H%M%S%f")}_{f.filename}')
        f.save(os.path.join(UPLOADS, guardado))
        c.execute("INSERT INTO cotizacion_archivos(cotizacion_id,nombre_original,nombre_archivo,creado) VALUES(?,?,?,?)",
                  (qid, original, guardado, now))
    c.execute(
        "UPDATE tramites SET estado='Cotizaciones en gestión' WHERE id=?", (tid,))
    c.execute("INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)",
              (tid, u['id'], 'Cotización guardada en borrador', now))
    c.commit()
    c.close()
    flash('Cotización guardada como borrador.', 'success')
    return redirect(url_for('logistics_quotations', tid=tid))


@app.route('/quotations/<int:qid>/send', methods=['POST'])
@login_required
def quotation_send(qid):
    u = user()
    if u['role'] != 'Logística':
        abort(403)
    c = db()
    q = c.execute(
        "SELECT q.*,t.tracking FROM cotizaciones q JOIN tramites t ON t.id=q.tramite_id WHERE q.id=?", (qid,)).fetchone()
    if not q or q['estado'] != 'Borrador':
        c.close()
        abort(400)
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute(
        "UPDATE cotizaciones SET estado='Enviada a Sara',enviado_fecha=? WHERE id=?", (now, qid))
    c.execute("INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)",
              (q['tramite_id'], u['id'], f'Cotización #{qid} enviada a Administración', now))
    sara = c.execute(
        "SELECT id FROM users WHERE role='Administración' AND active=1 ORDER BY id LIMIT 1").fetchone()
    c.commit()
    c.close()
    if sara:
        notify(sara['id'], q['tramite_id'], 'Cotización pendiente de revisión',
               f"Logística envió una cotización del requerimiento {q['tracking']} para revisión.", 'accion')
    flash('Cotización enviada a Sara.', 'success')
    return redirect(url_for('logistics_quotations', tid=q['tramite_id']))


@app.route('/quotations/files/<int:fid>')
@login_required
def quotation_file(fid):
    c = db()
    f = c.execute("SELECT * FROM cotizacion_archivos WHERE id=?",
                  (fid,)).fetchone()
    c.close()
    if not f:
        abort(404)
    return send_from_directory(UPLOADS, f['nombre_archivo'], as_attachment=False, download_name=f['nombre_original'])


@app.route('/logistics/<int:tid>/authorize', methods=['POST'])
@login_required
def quotation_authorize(tid):
    u = user()
    if u['role'] != 'Administración':
        abort(403)
    c = db()
    selections = request.form.getlist('selection[]')
    grouped = []
    # each value qid:itemid, with qty_{qid}_{itemid}
    current_auth = dict((r['item_id'], float(r['qty'] or 0)) for r in c.execute(
        "SELECT ai.item_id,SUM(ai.cantidad) qty FROM autorizacion_items ai JOIN autorizaciones_compra a ON a.id=ai.autorizacion_id WHERE a.tramite_id=? AND a.estado='Autorizada' GROUP BY ai.item_id", (tid,)).fetchall())
    for token in selections:
        try:
            qid, item_id = map(int, token.split(':'))
        except:
            continue
        qty = parse_decimal(request.form.get(f'qty_{qid}_{item_id}'))
        row = c.execute("""SELECT qi.*,q.proveedor_id,q.estado,i.comprar FROM cotizacion_items qi JOIN cotizaciones q ON q.id=qi.cotizacion_id JOIN items i ON i.id=qi.item_id WHERE qi.cotizacion_id=? AND qi.item_id=? AND q.tramite_id=?""", (qid, item_id, tid)).fetchone()
        if not row or row['estado'] != 'Enviada a Sara' or qty <= 0 or qty > float(row['cantidad'] or 0):
            continue
        pendiente = max(
            0, float(row['comprar'] or 0)-current_auth.get(item_id, 0))
        if qty > pendiente+0.0001:
            c.close()
            flash('Una cantidad autorizada supera lo pendiente por comprar.', 'danger')
            return redirect(url_for('logistics_quotations', tid=tid))
        grouped.append(
            (qid, item_id, row['proveedor_id'], qty, float(row['precio_unitario'])))
        current_auth[item_id] = current_auth.get(item_id, 0)+qty
    if not grouped:
        c.close()
        flash('Seleccione al menos una cantidad para autorizar.', 'danger')
        return redirect(url_for('logistics_quotations', tid=tid))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cur = c.execute(
        "INSERT INTO autorizaciones_compra(tramite_id,autorizado_por,estado,fecha) VALUES(?,?,'Autorizada',?)", (tid, u['id'], now))
    aid = cur.lastrowid
    totals = {}
    for qid, item_id, pid, qty, price in grouped:
        subtotal = qty*price
        c.execute("INSERT INTO autorizacion_items(autorizacion_id,cotizacion_id,item_id,proveedor_id,cantidad,precio_unitario,subtotal) VALUES(?,?,?,?,?,?,?)",
                  (aid, qid, item_id, pid, qty, price, subtotal))
        totals[pid] = totals.get(pid, 0)+subtotal
    yoana = c.execute(
        "SELECT id FROM users WHERE role='Tesorería' AND active=1 ORDER BY id LIMIT 1").fetchone()
    for pid, total in totals.items():
        prov = c.execute(
            "SELECT nombre FROM proveedores WHERE id=?", (pid,)).fetchone()
        c.execute("INSERT INTO solicitudes_tesoreria(tramite_id,origen,solicitado_por,motivo,monto,estado,fecha_solicitud,autorizacion_id,proveedor_id,proveedor_nombre) VALUES(?, 'REQ-Cotizacion', ?, ?, ?, 'Pendiente', ?, ?, ?, ?)",
                  (tid, u['id'], f'Compra autorizada - {prov["nombre"]}', total, now, aid, pid, prov['nombre']))
    c.execute("UPDATE tramites SET estado='En gestión de compra' WHERE id=?", (tid,))
    c.execute("INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)",
              (tid, u['id'], f'Compra autorizada parcialmente/completa - autorización #{aid}', now))
    c.commit()
    c.close()
    if yoana:
        notify(yoana['id'], tid, 'Pago de compra autorizado',
               f'Administración autorizó una compra del requerimiento. Tienes {len(totals)} pago(s) por atender.', 'accion')
    flash('Compra autorizada. Los pagos pendientes ya aparecen a Tesorería.', 'success')
    return redirect(url_for('logistics_quotations', tid=tid))


@app.route('/authorizations/<int:aid>/cancel', methods=['POST'])
@login_required
def authorization_cancel(aid):
    u = user()
    if u['role'] != 'Administración':
        abort(403)
    motivo = request.form.get('motivo', '').strip()
    c = db()
    a = c.execute(
        "SELECT * FROM autorizaciones_compra WHERE id=?", (aid,)).fetchone()
    if not a or a['estado'] != 'Autorizada':
        c.close()
        abort(400)
    paid = c.execute(
        "SELECT COUNT(*) n FROM solicitudes_tesoreria WHERE autorizacion_id=? AND estado='Atendido'", (aid,)).fetchone()['n']
    if paid:
        c.close()
        flash('No puede anular: Yoana ya atendió al menos un pago de esta autorización.', 'danger')
        return redirect(url_for('logistics_quotations', tid=a['tramite_id']))
    if not motivo:
        c.close()
        flash('Indique el motivo de anulación.', 'danger')
        return redirect(url_for('logistics_quotations', tid=a['tramite_id']))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute("UPDATE autorizaciones_compra SET estado='Anulada',motivo_anulacion=?,anulada_fecha=? WHERE id=?", (motivo, now, aid))
    c.execute("UPDATE solicitudes_tesoreria SET estado='Anulado',fecha_atencion=? WHERE autorizacion_id=? AND estado='Pendiente'", (now, aid))
    c.execute("INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)",
              (a['tramite_id'], u['id'], f'Autorización de compra #{aid} anulada: {motivo}', now))
    c.commit()
    c.close()
    flash('Autorización anulada.', 'success')
    return redirect(url_for('logistics_quotations', tid=a['tramite_id']))


@app.route('/logistics/<int:tid>/purchase', methods=['POST'])
@login_required
def logistics_purchase(tid):
    # Registra la gestión/cotización; no exige factura antes del pago.
    u = user()
    if u['role'] != 'Logística':
        abort(403)

    c = db()
    t = c.execute('SELECT * FROM tramites WHERE id=?', (tid,)).fetchone()
    if not t or t['tipo'] != 'REQ' or t['estado'] != 'Recibido por Logística':
        c.close()
        abort(400)

    # Por ahora usamos un solo dato: proveedor O RUC.
    # Si se escriben 11 dígitos, se guarda como RUC; si se escribe texto,
    # se guarda como nombre / razón social del proveedor.
    proveedor_o_ruc = request.form.get('proveedor_o_ruc', '').strip()
    monto = parse_decimal(request.form.get('monto_cotizado'))
    fecha = request.form.get('fecha_cotizacion', '').strip(
    ) or datetime.now().strftime('%Y-%m-%d')
    cotizacion = request.files.get('cotizacion')

    if not proveedor_o_ruc:
        c.close()
        flash('Ingrese el proveedor o el RUC de la compra.', 'danger')
        return redirect(url_for('request_detail', tid=tid))

    solo_digitos = re.sub(r'\D', '', proveedor_o_ruc)
    if proveedor_o_ruc.isdigit():
        if len(solo_digitos) != 11:
            c.close()
            flash('Si ingresas un RUC, debe tener 11 dígitos.', 'danger')
            return redirect(url_for('request_detail', tid=tid))
        ruc = solo_digitos
        proveedor = ruc
    else:
        proveedor = proveedor_o_ruc
        ruc = ''
    if monto <= 0:
        c.close()
        flash('Ingrese un monto cotizado mayor que 0.', 'danger')
        return redirect(url_for('request_detail', tid=tid))
    if cotizacion and cotizacion.filename and not cotizacion.filename.lower().endswith(('.pdf', '.png', '.jpg', '.jpeg', '.webp')):
        c.close()
        flash('La cotización debe ser PDF o imagen.', 'danger')
        return redirect(url_for('request_detail', tid=tid))

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute(
        'INSERT OR IGNORE INTO gestion_logistica(tramite_id) VALUES(?)', (tid,))
    c.execute('''UPDATE gestion_logistica SET proveedor=?, ruc=?, proveedor_cotizado=?, ruc_proveedor=?,
                 fecha_cotizacion=?, monto_cotizado=?, monto=?, fecha_compra=NULL, tipo_comprobante=NULL,
                 nro_comprobante=NULL, comprobante_pendiente=1, forma_pago=NULL, estado_pago=NULL,
                 requiere_reembolso=0 WHERE tramite_id=?''',
              (proveedor, ruc, proveedor, ruc, fecha, monto, monto, tid))

    if cotizacion and cotizacion.filename:
        save_logistics_files(c, tid, [cotizacion], 'cotizacion')

    c.execute("UPDATE tramites SET estado='En gestión de compra' WHERE id=?", (tid,))
    c.execute('INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)',
              (tid, u['id'], f'Cotización registrada por Logística - S/ {monto:,.2f}', now))
    c.commit()
    c.close()
    flash('Gestión de compra registrada. Ahora define cómo se realizará el pago.', 'success')
    return redirect(url_for('request_detail', tid=tid))


@app.route('/logistics/<int:tid>/receipts', methods=['POST'])
@login_required
def logistics_receipts(tid):
    # Registra los comprobantes definitivos una vez gestionado el pago.
    u = user()
    if u['role'] != 'Logística':
        abort(403)
    c = db()
    t = c.execute('SELECT * FROM tramites WHERE id=?', (tid,)).fetchone()
    g = c.execute(
        'SELECT * FROM gestion_logistica WHERE tramite_id=?', (tid,)).fetchone()
    if not t or not g or g['estado_pago'] not in ('Pagado por Logística', 'Pagado por Tesorería'):
        c.close()
        flash('Primero debe quedar gestionado el pago.', 'danger')
        return redirect(url_for('request_detail', tid=tid))

    fechas = request.form.getlist('fecha_compra[]')
    tipos = request.form.getlist('tipo_comprobante[]')
    numeros = request.form.getlist('nro_comprobante[]')
    montos = request.form.getlist('monto[]')
    archivos = request.files.getlist('comprobante[]')
    filas = []
    n = max(len(fechas), len(tipos), len(numeros), len(montos), len(archivos))
    permitidos = ('.pdf', '.png', '.jpg', '.jpeg', '.webp', '.xml')
    for i in range(n):
        fecha = fechas[i].strip() if i < len(fechas) else ''
        tipo = tipos[i].strip() if i < len(tipos) else ''
        numero = numeros[i].strip() if i < len(numeros) else ''
        monto = parse_decimal(montos[i] if i < len(montos) else '')
        archivo = archivos[i] if i < len(archivos) else None
        if not any((fecha, tipo, numero, monto, archivo and archivo.filename)):
            continue
        if not fecha or tipo not in ('Factura', 'Boleta', 'Otro') or not numero or monto <= 0 or not archivo or not archivo.filename:
            c.close()
            flash(
                'Complete la fecha, tipo, número, monto y evidencia de cada comprobante.', 'danger')
            return redirect(url_for('request_detail', tid=tid))
        if not archivo.filename.lower().endswith(permitidos):
            c.close()
            flash('La evidencia debe ser PDF, imagen o XML.', 'danger')
            return redirect(url_for('request_detail', tid=tid))
        filas.append((fecha, tipo, numero, monto, archivo))
    if not filas:
        c.close()
        flash('Registra al menos un comprobante definitivo.', 'danger')
        return redirect(url_for('request_detail', tid=tid))

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    total = 0
    nums = []
    for idx, (fecha, tipo, numero, monto, archivo) in enumerate(filas, 1):
        original = archivo.filename
        guardado = secure_filename(
            f'COMPRA_{tid}_{idx}_{datetime.now().strftime("%Y%m%d%H%M%S%f")}_{archivo.filename}')
        archivo.save(os.path.join(UPLOADS, guardado))
        c.execute('''INSERT INTO compras_logistica(tramite_id,fecha_compra,tipo_comprobante,nro_comprobante,monto,nombre_original,nombre_archivo,creado)
                     VALUES(?,?,?,?,?,?,?,?)''', (tid, fecha, tipo, numero, monto, original, guardado, now))
        total += monto
        nums.append(numero)
    c.execute('''UPDATE gestion_logistica SET fecha_compra=?,tipo_comprobante=?,nro_comprobante=?,comprobante_pendiente=1 WHERE tramite_id=?''',
              (filas[0][0], filas[0][1] if len(filas) == 1 else 'Múltiples', ', '.join(nums), tid))
    create_regularization(c, tid, u['id'], 'Regularización de compra',
                          'Detallar los materiales comprados, cantidades y comprobante que sustenta cada adquisición.')
    c.execute('INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)', (tid,
              u['id'], f'Comprobante(s) definitivo(s) registrados por Logística - S/ {total:,.2f}', now))
    c.commit()
    c.close()
    flash('Comprobante(s) de compra registrados. Ya puede continuar con el despacho.', 'success')
    return redirect(url_for('request_detail', tid=tid))


@app.route('/logistics/<int:tid>/regularize-purchase', methods=['POST'])
@login_required
def logistics_regularize_purchase(tid):
    u = user()

    if u['role'] not in ('Logística', 'Sistemas'):
        abort(403)

    obra = active_project()
    if not obra:
        abort(403)

    c = db()

    t = c.execute(
        '''
        SELECT *
        FROM tramites
        WHERE id=?
        AND obra_id=?
        AND tipo='REQ'
        ''',
        (tid, obra['id'])
    ).fetchone()

    if not t:
        c.close()
        abort(400)

    regularizacion = c.execute(
        '''
        SELECT *
        FROM regularizaciones
        WHERE tramite_id=?
        AND tipo='Regularización de compra'
        AND estado='Pendiente'
        ORDER BY id DESC
        LIMIT 1
        ''',
        (tid,)
    ).fetchone()

    if not regularizacion:
        c.close()
        flash(
            'Este requerimiento no tiene regularización de compra pendiente.',
            'danger'
        )
        return redirect(url_for('logistics_regularizations'))

    compras = c.execute(
        '''
        SELECT *
        FROM compras_logistica
        WHERE tramite_id=?
        ORDER BY id
        ''',
        (tid,)
    ).fetchall()

    if not compras:
        c.close()
        flash(
            'No hay comprobantes registrados para este requerimiento.',
            'danger'
        )
        return redirect(url_for('logistics_regularizations'))

    total_comprobantes = round(
        sum(float(compra['monto'] or 0) for compra in compras),
        2
    )

    descripciones = request.form.getlist('descripcion[]')
    cantidades = request.form.getlist('cantidad[]')
    precios_unitarios = request.form.getlist('precio_unitario[]')

    detalles = []

    max_filas = max(
        len(descripciones),
        len(cantidades),
        len(precios_unitarios)
    )

    for i in range(max_filas):
        descripcion = (
            descripciones[i].strip()
            if i < len(descripciones)
            else ''
        )

        cantidad_txt = (
            cantidades[i].strip()
            if i < len(cantidades)
            else ''
        )

        precio_unitario_txt = (
            precios_unitarios[i].strip()
            if i < len(precios_unitarios)
            else ''
        )

        # Una fila completamente vacía se ignora.
        if not descripcion and not cantidad_txt and not precio_unitario_txt:
            continue

        if not descripcion:
            c.close()
            flash(
                'Cada producto de la regularización debe tener descripción.',
                'danger'
            )
            return redirect(
                url_for('logistics_regularization_detail', tid=tid)
            )

        if not cantidad_txt:
            c.close()
            flash(
                f'Ingrese la cantidad de "{descripcion}".',
                'danger'
            )
            return redirect(
                url_for('logistics_regularization_detail', tid=tid)
            )

        if not precio_unitario_txt:
            c.close()
            flash(
                f'Ingrese el precio unitario de "{descripcion}".',
                'danger'
            )
            return redirect(
                url_for('logistics_regularization_detail', tid=tid)
            )

        cantidad = parse_decimal(cantidad_txt)
        precio_unitario = parse_decimal(precio_unitario_txt)

        if cantidad <= 0:
            c.close()
            flash(
                f'La cantidad de "{descripcion}" debe ser mayor que 0.',
                'danger'
            )
            return redirect(
                url_for('logistics_regularization_detail', tid=tid)
            )

        if precio_unitario < 0:
            c.close()
            flash(
                f'El precio unitario de "{descripcion}" no puede ser negativo.',
                'danger'
            )
            return redirect(
                url_for('logistics_regularization_detail', tid=tid)
            )

        precio_total = round(cantidad * precio_unitario, 2)

        detalles.append(
            (
                descripcion,
                cantidad,
                precio_unitario,
                precio_total
            )
        )

    if not detalles:
        c.close()
        flash(
            'Debes registrar al menos un producto en la regularización.',
            'danger'
        )
        return redirect(
            url_for('logistics_regularization_detail', tid=tid)
        )

    total_detalle = round(
        sum(detalle[3] for detalle in detalles),
        2
    )

    # La regularización solo se completa si el detalle cuadra
    # exactamente con el total de los comprobantes registrados.
    if abs(total_detalle - total_comprobantes) > 0.01:
        c.close()

        diferencia = round(
            total_comprobantes - total_detalle,
            2
        )

        if diferencia > 0:
            mensaje_diferencia = (
                f'Faltan S/ {diferencia:,.2f} por detallar.'
            )
        else:
            mensaje_diferencia = (
                f'El detalle excede en S/ {abs(diferencia):,.2f}.'
            )

        flash(
            (
                'No puede completar la regularización. '
                f'El total de los productos es S/ {total_detalle:,.2f}, '
                f'pero el total de los comprobantes es '
                f'S/ {total_comprobantes:,.2f}. '
                + mensaje_diferencia
            ),
            'danger'
        )

        return redirect(
            url_for('logistics_regularization_detail', tid=tid)
        )

    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    c.execute(
        '''
        DELETE FROM regularizacion_compra_detalle
        WHERE tramite_id=?
        ''',
        (tid,)
    )

    for descripcion, cantidad, precio_unitario, precio_total in detalles:
        c.execute(
            '''
            INSERT INTO regularizacion_compra_detalle(
                tramite_id,
                descripcion,
                cantidad,
                precio_unitario,
                precio_total,
                creado
            )
            VALUES(?,?,?,?,?,?)
            ''',
            (
                tid,
                descripcion,
                cantidad,
                precio_unitario,
                precio_total,
                now
            )
        )

    c.execute(
        '''
        UPDATE gestion_logistica
        SET comprobante_pendiente=0
        WHERE tramite_id=?
        ''',
        (tid,)
    )

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
            regularizacion['id']
        )
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
            (
                'Regularización de compra completada por Logística - '
                f'Total detallado S/ {total_detalle:,.2f}'
            ),
            now
        )
    )

    c.commit()
    c.close()

    flash(
        (
            'Regularización completada correctamente. '
            f'El detalle coincide con el total de comprobantes: '
            f'S/ {total_comprobantes:,.2f}.'
        ),
        'success'
    )

    return redirect(
        url_for('logistics_regularizations')
    )


@app.route('/logistics/regularizations')
@login_required
def logistics_regularizations():
    u = user()

    if u['role'] not in ('Logística', 'Sistemas'):
        abort(403)

    obra = active_project()
    if not obra:
        abort(403)

    c = db()

    pendientes = c.execute(
        '''
        SELECT
            r.*,
            t.tracking,
            t.numero,
            t.fecha,
            t.estado AS tramite_estado,
            creador.full_name AS creador_nombre,
            (
                SELECT COUNT(*)
                FROM compras_logistica cl
                WHERE cl.tramite_id=t.id
            ) AS comprobantes,
            (
                SELECT COALESCE(SUM(cl.monto),0)
                FROM compras_logistica cl
                WHERE cl.tramite_id=t.id
            ) AS total_compra
        FROM regularizaciones r
        JOIN tramites t
            ON t.id=r.tramite_id
        LEFT JOIN users creador
            ON creador.id=t.creador
        WHERE t.obra_id=?
        AND r.tipo='Regularización de compra'
        AND r.estado='Pendiente'
        ORDER BY r.id DESC
        ''',
        (obra['id'],)
    ).fetchall()

    completadas = c.execute(
        '''
        SELECT
            r.*,
            t.tracking,
            t.numero,
            t.fecha,
            t.estado AS tramite_estado,
            creador.full_name AS creador_nombre,
            (
                SELECT COUNT(*)
                FROM compras_logistica cl
                WHERE cl.tramite_id=t.id
            ) AS comprobantes,
            (
                SELECT COALESCE(SUM(cl.monto),0)
                FROM compras_logistica cl
                WHERE cl.tramite_id=t.id
            ) AS total_compra
        FROM regularizaciones r
        JOIN tramites t
            ON t.id=r.tramite_id
        LEFT JOIN users creador
            ON creador.id=t.creador
        WHERE t.obra_id=?
        AND r.tipo='Regularización de compra'
        AND r.estado='Regularizado'
        ORDER BY r.fecha_regularizacion DESC, r.id DESC
        LIMIT 50
        ''',
        (obra['id'],)
    ).fetchall()

    c.close()

    return render_template(
        'logistics_regularizations.html',
        user=u,
        pendientes=pendientes,
        completadas=completadas
    )


@app.route('/logistics/regularizations/<int:tid>')
@login_required
def logistics_regularization_detail(tid):
    u = user()

    if u['role'] not in ('Logística', 'Sistemas'):
        abort(403)

    obra = active_project()
    if not obra:
        abort(403)

    c = db()

    t = c.execute(
        '''
        SELECT
            t.*,
            u.full_name AS creador_nombre
        FROM tramites t
        JOIN users u
            ON u.id=t.creador
        WHERE t.id=?
        AND t.obra_id=?
        AND t.tipo='REQ'
        ''',
        (
            tid,
            obra['id']
        )
    ).fetchone()

    if not t:
        c.close()
        abort(404)

    regularizacion = c.execute(
        '''
        SELECT *
        FROM regularizaciones
        WHERE tramite_id=?
        AND tipo='Regularización de compra'
        ORDER BY id DESC
        LIMIT 1
        ''',
        (tid,)
    ).fetchone()

    if not regularizacion:
        c.close()
        abort(404)

    compras = c.execute(
        '''
        SELECT *
        FROM compras_logistica
        WHERE tramite_id=?
        ORDER BY id
        ''',
        (tid,)
    ).fetchall()

    detalles = c.execute(
        '''
        SELECT *
        FROM regularizacion_compra_detalle
        WHERE tramite_id=?
        ORDER BY id
        ''',
        (tid,)
    ).fetchall()

    total_comprobantes = round(
        sum(float(compra['monto'] or 0) for compra in compras),
        2
    )

    total_detalle = round(
        sum(float(detalle['precio_total'] or 0) for detalle in detalles),
        2
    )

    c.close()

    return render_template(
        'logistics_regularization_detail.html',
        user=u,
        t=t,
        regularizacion=regularizacion,
        compras=compras,
        detalles=detalles,
        total_comprobantes=total_comprobantes,
        total_detalle=total_detalle
    )


@app.route('/logistics/purchases/<int:pid>/view')
@login_required
def view_purchase_file(pid):
    u = user()
    c = db()

    compra = c.execute(
        '''
        SELECT cl.*, t.obra_id
        FROM compras_logistica cl
        JOIN tramites t ON t.id=cl.tramite_id
        WHERE cl.id=?
        ''',
        (pid,)
    ).fetchone()

    c.close()

    if not compra or compra['obra_id'] != active_project()['id']:
        abort(404)

    if not compra['nombre_archivo']:
        abort(404)

    return send_from_directory(
        UPLOADS,
        compra['nombre_archivo'],
        as_attachment=False,
        download_name=compra['nombre_original']
    )


@app.route('/logistics/purchases/<int:pid>/download')
@login_required
def download_purchase_file(pid):
    c = db()

    compra = c.execute(
        '''
        SELECT cl.*, t.obra_id
        FROM compras_logistica cl
        JOIN tramites t ON t.id=cl.tramite_id
        WHERE cl.id=?
        ''',
        (pid,)
    ).fetchone()

    c.close()

    if not compra or compra['obra_id'] != active_project()['id']:
        abort(404)

    if not compra['nombre_archivo']:
        abort(404)

    return send_from_directory(
        UPLOADS,
        compra['nombre_archivo'],
        as_attachment=True,
        download_name=compra['nombre_original']
    )


@app.route('/logistics/<int:tid>/payment', methods=['POST'])
@login_required
def logistics_payment(tid):
    u = user()
    if u['role'] != 'Logística':
        abort(403)
    c = db()
    t = c.execute("SELECT * FROM tramites WHERE id=?", (tid,)).fetchone()
    gestion = c.execute(
        "SELECT * FROM gestion_logistica WHERE tramite_id=?", (tid,)).fetchone()
    if not t or not gestion:
        c.close()
        abort(404)
    monto = float(gestion['monto'] or 0)
    forma = request.form.get('forma_pago', '').strip()
    if monto >= 1900:
        forma = 'Tesorería'
    if forma not in ('Caja Logística', 'Tesorería', 'Reembolso', 'Pago personal'):
        c.close()
        flash('Seleccione una forma de pago.', 'danger')
        return redirect(url_for('request_detail', tid=tid))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    yoana = c.execute(
        "SELECT id FROM users WHERE role='Tesorería' AND active=1 ORDER BY id LIMIT 1").fetchone()
    yoana_id = yoana['id'] if yoana else None
    if forma == 'Tesorería':
        c.execute("UPDATE gestion_logistica SET forma_pago='Tesorería', estado_pago='Pendiente de Tesorería', requiere_reembolso=0 WHERE tramite_id=?", (tid,))
        existe = c.execute(
            "SELECT id FROM solicitudes_tesoreria WHERE tramite_id=? AND origen='REQ-Compra' AND estado='Pendiente'", (tid,)).fetchone()
        if not existe:
            c.execute("INSERT INTO solicitudes_tesoreria(tramite_id,origen,solicitado_por,motivo,monto,estado,fecha_solicitud) VALUES(?,?,?,?,?,'Pendiente',?)",
                      (tid, 'REQ-Compra', u['id'], 'Pago de compra del requerimiento', monto, now))
        accion = 'Pago derivado a Tesorería' if monto >= 1900 else 'Fondos solicitados a Tesorería'
        titulo = 'Pago requerido'
        mensaje = f"El requerimiento {t['tracking']} requiere un pago de S/ {monto:,.2f}."
    elif forma == 'Caja Logística':
        c.execute("UPDATE gestion_logistica SET forma_pago='Caja Logística', estado_pago='Pagado por Logística', requiere_reembolso=0 WHERE tramite_id=?", (tid,))
        existe = c.execute(
            "SELECT id FROM solicitudes_tesoreria WHERE tramite_id=? AND origen='REQ-Caja-Logistica'", (tid,)).fetchone()
        if not existe:
            c.execute("INSERT INTO solicitudes_tesoreria(tramite_id,origen,solicitado_por,motivo,monto,estado,fecha_solicitud,fecha_atencion) VALUES(?,?,?,?,?,'Informativo',?,?)",
                      (tid, 'REQ-Caja-Logistica', u['id'], 'Pago realizado con Caja de Logística', monto, now, now))
        accion = 'Pago realizado con Caja de Logística'
        titulo = 'Control de Caja de Logística'
        mensaje = f"Rodrigo registró un pago de S/ {monto:,.2f} con Caja de Logística para {t['tracking']}."
    else:
        forma = 'Pago personal'
        c.execute("UPDATE gestion_logistica SET forma_pago='Pago personal', estado_pago='Pagado por Logística', requiere_reembolso=1 WHERE tramite_id=?", (tid,))
        existe = c.execute(
            "SELECT id FROM solicitudes_tesoreria WHERE tramite_id=? AND origen='REQ-Reembolso' AND estado='Pendiente'", (tid,)).fetchone()
        if not existe:
            c.execute("INSERT INTO solicitudes_tesoreria(tramite_id,origen,solicitado_por,motivo,monto,estado,fecha_solicitud) VALUES(?,?,?,?,?,'Pendiente',?)",
                      (tid, 'REQ-Reembolso', u['id'], 'Reembolso de pago personal', monto, now))
        accion = 'Pago personal registrado; reembolso solicitado a Tesorería'
        titulo = 'Reembolso pendiente'
        mensaje = f"El requerimiento {t['tracking']} tiene un reembolso pendiente por S/ {monto:,.2f}."
    c.execute("INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)",
              (tid, u['id'], accion, now))
    c.commit()
    c.close()
    if yoana_id:
        notify(yoana_id, tid, titulo, mensaje, 'accion' if forma !=
               'Caja Logística' else 'informativa')
    flash('Gestión de pago registrada correctamente.', 'success')
    return redirect(url_for('request_detail', tid=tid))


@app.route('/treasury')
@login_required
def treasury_dashboard():
    u = user()
    if u['role'] not in ('Tesorería', 'Sistemas'):
        abort(403)
    obra = active_project()
    if not obra:
        abort(403)
    c = db()
    q = """SELECT s.*,t.tracking,t.numero,t.fecha,t.estado AS tramite_estado,solicitante.full_name AS solicitado_por_nombre,g.forma_pago,g.estado_pago,(SELECT COUNT(*) FROM compras_logistica cl WHERE cl.tramite_id=t.id) AS comprobantes FROM solicitudes_tesoreria s JOIN tramites t ON t.id=s.tramite_id LEFT JOIN users solicitante ON solicitante.id=s.solicitado_por LEFT JOIN gestion_logistica g ON g.tramite_id=t.id WHERE t.obra_id=?"""
    pagos_compra = c.execute(
        q+" AND s.origen IN ('REQ-Cotizacion','REQ-Compra') ORDER BY CASE WHEN s.estado='Pendiente' THEN 0 ELSE 1 END,s.id DESC", (obra['id'],)).fetchall()
    reembolsos = c.execute(
        q+" AND s.estado='Pendiente' AND s.origen='REQ-Reembolso' ORDER BY s.id DESC", (obra['id'],)).fetchall()
    sp_pagos = c.execute('''
        SELECT t.id,t.tracking,t.numero,t.fecha,t.estado,t.beneficiario,t.abono,t.moneda,
               g.asignado_pago,g.fecha_asignacion,
               COALESCE((SELECT SUM(p.monto) FROM sp_pagos_multiples p WHERE p.tramite_id=t.id),0) AS pagado
        FROM tramites t
        JOIN gestion_sp g ON g.tramite_id=t.id
        WHERE t.obra_id=? AND t.tipo='SP' AND g.asignado_pago='Tesorería'
        ORDER BY CASE WHEN t.estado IN ('Asignada a Tesorería','Pago parcial') THEN 0 ELSE 1 END,t.id DESC
    ''', (obra['id'],)).fetchall()
    rr_pendientes = c.execute(
        "SELECT COUNT(*) n FROM reembolsos_rendiciones WHERE obra_id=? AND estado='Pendiente de Tesorería'", (obra['id'],)).fetchone()['n']
    c.close()
    return render_template('treasury.html', user=u, reembolsos=reembolsos, pagos_compra=pagos_compra, sp_pagos=sp_pagos, rr_pendientes=rr_pendientes)


@app.route('/treasury/<int:sid>')
@login_required
def treasury_detail(sid):
    u = user()
    if u['role'] not in ('Tesorería', 'Sistemas'):
        abort(403)
    obra = active_project()
    c = db()
    solicitud = c.execute(
        """SELECT s.*,t.tracking,t.numero,t.fecha,t.estado AS tramite_estado,solicitante.full_name AS solicitado_por_nombre FROM solicitudes_tesoreria s JOIN tramites t ON t.id=s.tramite_id LEFT JOIN users solicitante ON solicitante.id=s.solicitado_por WHERE s.id=? AND t.obra_id=?""", (sid, obra['id'])).fetchone()
    if not solicitud:
        c.close()
        abort(404)
    compras = c.execute("SELECT * FROM compras_logistica WHERE tramite_id=? ORDER BY id",
                        (solicitud['tramite_id'],)).fetchall()
    pagos = c.execute(
        "SELECT * FROM pagos_tesoreria WHERE solicitud_id=? ORDER BY id", (sid,)).fetchall()
    c.close()
    return render_template('treasury_payment_detail.html', user=u, solicitud=solicitud, compras=compras, pagos=pagos, fecha_hoy=datetime.now().strftime('%Y-%m-%d'))


@app.route('/treasury/payment-files/<int:pid>/<mode>')
@login_required
def treasury_payment_file(pid, mode):
    u = user()
    if u['role'] not in ('Tesorería', 'Logística', 'Sistemas'):
        abort(403)
    c = db()
    pago = c.execute(
        "SELECT p.*,s.tramite_id FROM pagos_tesoreria p JOIN solicitudes_tesoreria s ON s.id=p.solicitud_id WHERE p.id=?", (pid,)).fetchone()
    c.close()
    if not pago or not pago['nombre_archivo']:
        abort(404)
    return send_from_directory(UPLOADS, pago['nombre_archivo'], as_attachment=(mode == 'download'), download_name=pago['nombre_original'])


@app.route('/treasury/<int:sid>/pay', methods=['POST'])
@login_required
def treasury_pay(sid):
    u = user()
    if u['role'] != 'Tesorería':
        abort(403)
    c = db()
    solicitud = c.execute(
        "SELECT s.*,t.tracking FROM solicitudes_tesoreria s JOIN tramites t ON t.id=s.tramite_id WHERE s.id=?", (sid,)).fetchone()
    if not solicitud:
        c.close()
        abort(404)
    if solicitud['estado'] != 'Pendiente':
        c.close()
        flash('Esta solicitud ya fue atendida.', 'warning')
        return redirect(url_for('treasury_detail', sid=sid))
    medio = request.form.get('medio_pago', '').strip()
    banco = request.form.get('banco', '').strip()
    fecha = request.form.get('fecha_pago', '').strip()
    monto = parse_decimal(request.form.get('monto'))
    nro = request.form.get('nro_operacion', '').strip()
    evidencias = [f for f in request.files.getlist(
        'comprobante_pago') if f and f.filename]
    if not medio or not fecha or monto <= 0:
        c.close()
        flash('Complete medio de pago, fecha y monto.', 'danger')
        return redirect(url_for('treasury_detail', sid=sid))
    if abs(float(monto)-float(solicitud['monto'] or 0)) > 0.01:
        c.close()
        flash(
            f"El monto debe ser exactamente S/ {float(solicitud['monto'] or 0):,.2f}.", 'danger')
        return redirect(url_for('treasury_detail', sid=sid))
    if medio != 'Efectivo' and not evidencias:
        c.close()
        flash('Debes adjuntar al menos una evidencia para transferencia, depósito, Yape, Plin u otro medio no efectivo.', 'danger')
        return redirect(url_for('treasury_detail', sid=sid))
    permitidos = ('.pdf', '.png', '.jpg', '.jpeg', '.webp')
    if any(not f.filename.lower().endswith(permitidos) for f in evidencias):
        c.close()
        flash('Las evidencias deben ser PDF o imagen.', 'danger')
        return redirect(url_for('treasury_detail', sid=sid))
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    for idx, f in enumerate(evidencias, 1):
        original = f.filename
        guardado = secure_filename(
            f"TES_{sid}_{int(datetime.now().timestamp())}_{idx}_{f.filename}")
        f.save(os.path.join(UPLOADS, guardado))
        c.execute("INSERT INTO pagos_tesoreria(solicitud_id,medio_pago,banco,monto,nro_operacion,nombre_original,nombre_archivo,fecha) VALUES(?,?,?,?,?,?,?,?)",
                  (sid, medio, banco, monto, nro, original, guardado, fecha))
    c.execute(
        "UPDATE solicitudes_tesoreria SET estado='Atendido',fecha_atencion=? WHERE id=?", (now, sid))
    tid = solicitud['tramite_id']
    if solicitud['origen'] in ('REQ-Compra', 'REQ-Cotizacion'):
        pendientes = c.execute(
            "SELECT COUNT(*) n FROM solicitudes_tesoreria WHERE tramite_id=? AND origen='REQ-Cotizacion' AND estado='Pendiente'", (tid,)).fetchone()['n']
        if pendientes == 0:
            c.execute(
                "INSERT OR IGNORE INTO gestion_logistica(tramite_id) VALUES(?)", (tid,))
            c.execute(
                "UPDATE gestion_logistica SET estado_pago='Pagado por Tesorería' WHERE tramite_id=?", (tid,))
        accion = 'Pago registrado por Tesorería'
        titulo = 'Pago realizado por Tesorería'
        if pendientes == 0:
            mensaje = f"Todos los pagos autorizados del requerimiento {solicitud['tracking']} fueron atendidos por Tesorería. Logística puede continuar con el comprobante definitivo."
        else:
            mensaje = f"Tesorería atendió un pago del requerimiento {solicitud['tracking']}. Quedan {pendientes} pago(s) pendiente(s)."
    else:
        c.execute(
            "UPDATE gestion_logistica SET requiere_reembolso=0 WHERE tramite_id=?", (tid,))
        accion = 'Reembolso atendido por Tesorería'
        titulo = 'Reembolso realizado'
        mensaje = f"El reembolso correspondiente al requerimiento {solicitud['tracking']} fue atendido por Tesorería."
    c.execute("INSERT INTO history(tramite_id,usuario_id,accion,fecha) VALUES(?,?,?,?)",
              (tid, u['id'], accion, now))
    rodrigo = c.execute(
        "SELECT id FROM users WHERE role='Logística' AND active=1 ORDER BY id LIMIT 1").fetchone()
    c.commit()
    c.close()
    if rodrigo:
        notify(rodrigo['id'], tid, titulo, mensaje,
               'accion' if solicitud['origen'] in ('REQ-Compra', 'REQ-Cotizacion') else 'informativa')
    flash('Pago registrado correctamente.', 'success')
    return redirect(url_for('treasury_dashboard'))


@app.route('/logistics/<int:tid>/dispatch', methods=['POST'])
@login_required
def logistics_dispatch(tid):
    u = user()

    if u['role'] != 'Logística':
        abort(403)

    # ==========================================
    # DATOS DEL ENVÍO
    # ==========================================

    medio_envio = request.form.get('medio_envio', '').strip()
    responsable_transporte = request.form.get(
        'responsable_transporte',
        ''
    ).strip()

    costo_envio_txt = request.form.get(
        'costo_envio',
        ''
    ).strip()

    observacion_envio = request.form.get(
        'observacion_envio',
        ''
    ).strip()

    guia_numero = request.form.get(
        'guia_numero',
        ''
    ).strip()

    guia_fecha = request.form.get(
        'guia_fecha',
        ''
    ).strip()

    guia_opcion = request.form.get('guia_opcion', '').strip()
    guia_pendiente = 1 if guia_opcion == 'pendiente' else 0

    # ==========================================
    # ARCHIVOS
    # ==========================================

    archivos_guia = [
        archivo
        for archivo in request.files.getlist('guia')
        if archivo and archivo.filename
    ]

    comprobantes_transporte = [
        archivo
        for archivo in request.files.getlist(
            'comprobante_transporte'
        )
        if archivo and archivo.filename
    ]

    evidencias_envio = [
        archivo
        for archivo in request.files.getlist(
            'evidencia_envio'
        )
        if archivo and archivo.filename
    ]

    # ==========================================
    # VALIDAR MEDIO DE ENVÍO
    # ==========================================

    medios_permitidos = (
        'Chofer de LANR',
        'Encomienda',
        'Transporte del proveedor',
        'Otro'
    )

    if medio_envio not in medios_permitidos:
        flash(
            'Seleccione un medio de envío.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=tid
            )
        )

    # ==========================================
    # VALIDAR COSTO DE ENVÍO
    # ==========================================

    costo_envio = 0.0

    if costo_envio_txt:

        costo_envio = parse_decimal(
            costo_envio_txt
        )

        if costo_envio < 0:
            flash(
                'El costo adicional de envío no puede ser negativo.',
                'danger'
            )

            return redirect(
                url_for(
                    'request_detail',
                    tid=tid
                )
            )

    # ==========================================
    # EVIDENCIA DEL ENVÍO OBLIGATORIA
    # ==========================================

    if not evidencias_envio:
        flash(
            'Debes adjuntar al menos una evidencia del envío.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=tid
            )
        )

    extensiones_imagen = (
        '.jpg',
        '.jpeg',
        '.png',
        '.webp'
    )

    for archivo in evidencias_envio:

        if not archivo.filename.lower().endswith(
            extensiones_imagen
        ):
            flash(
                'La evidencia del envío debe ser una imagen '
                '(JPG, JPEG, PNG o WEBP).',
                'danger'
            )

            return redirect(
                url_for(
                    'request_detail',
                    tid=tid
                )
            )

    # ==========================================
    # GUÍA: EL USUARIO ELIGE UNA DE DOS OPCIONES
    # ==========================================

    if guia_opcion not in ('adjuntar', 'pendiente'):
        flash('Seleccione una opción para la guía de remisión.', 'danger')
        return redirect(url_for('request_detail', tid=tid))

    if guia_opcion == 'adjuntar':
        if not guia_numero or not guia_fecha or not archivos_guia:
            flash(
                'Para adjuntar la guía, complete el número, la fecha y el archivo.', 'danger')
            return redirect(url_for('request_detail', tid=tid))
    else:
        # Si se regularizará después, no se guardan datos parciales de guía.
        guia_numero = ''
        guia_fecha = ''
        archivos_guia = []

    extensiones_documento = (
        '.pdf',
        '.jpg',
        '.jpeg',
        '.png',
        '.webp'
    )

    for archivo in archivos_guia:

        if not archivo.filename.lower().endswith(
            extensiones_documento
        ):
            flash(
                'La guía de remisión debe ser PDF o imagen.',
                'danger'
            )

            return redirect(
                url_for(
                    'request_detail',
                    tid=tid
                )
            )

    # ==========================================
    # COMPROBANTE DEL TRANSPORTE OPCIONAL
    # ==========================================

    for archivo in comprobantes_transporte:

        if not archivo.filename.lower().endswith(
            extensiones_documento
        ):
            flash(
                'El comprobante del transporte debe ser PDF o imagen.',
                'danger'
            )

            return redirect(
                url_for(
                    'request_detail',
                    tid=tid
                )
            )

    # ==========================================
    # CONSULTAR REQUERIMIENTO
    # ==========================================

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

    if t['tipo'] != 'REQ':
        c.close()
        abort(400)

    # ==========================================
    # VALIDAR GESTIÓN LOGÍSTICA
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

    # ==========================================
    # SOLO SE DESPACHA SI YA FUE PAGADO
    # ==========================================

    if gestion['estado_pago'] not in (
        'Pagado por Logística',
        'Pagado por Tesorería'
    ):
        c.close()

        flash(
            'No puede enviar a obra mientras el pago esté pendiente.',
            'danger'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=tid
            )
        )

    # ==========================================
    # EVITAR DOBLE DESPACHO
    # ==========================================

    if t['estado'] in (
        'Enviado a obra',
        'Cerrado'
    ):
        c.close()

        flash(
            'Este requerimiento ya fue enviado a obra.',
            'warning'
        )

        return redirect(
            url_for(
                'request_detail',
                tid=tid
            )
        )

    # ==========================================
    # GUARDAR ARCHIVOS
    # ==========================================

    save_logistics_files(
        c,
        tid,
        archivos_guia,
        'guia'
    )

    save_logistics_files(
        c,
        tid,
        comprobantes_transporte,
        'comprobante_transporte'
    )

    save_logistics_files(
        c,
        tid,
        evidencias_envio,
        'evidencia_envio'
    )

    now = datetime.now().strftime(
        '%Y-%m-%d %H:%M:%S'
    )

    # ==========================================
    # GUARDAR INFORMACIÓN DEL DESPACHO
    # ==========================================

    c.execute(
        '''
        UPDATE gestion_logistica
        SET
            medio_envio=?,
            responsable_transporte=?,
            costo_envio=?,
            observacion_envio=?,
            guia_numero=?,
            guia_fecha=?,
            guia_pendiente=?,
            enviado_fecha=?
        WHERE tramite_id=?
        ''',
        (
            medio_envio,
            responsable_transporte,
            costo_envio,
            observacion_envio,
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
            (
                'Regularizar la guía de remisión '
                'correspondiente al envío a obra.'
            )
        )

    # ==========================================
    # CAMBIAR ESTADO
    # ==========================================

    c.execute(
        '''
        UPDATE tramites
        SET estado='Enviado a obra'
        WHERE id=?
        ''',
        (tid,)
    )

    # ==========================================
    # HISTORIAL
    # ==========================================

    descripcion_historial = (
        f'Material enviado a obra mediante {medio_envio}'
    )

    if responsable_transporte:
        descripcion_historial += (
            f' - {responsable_transporte}'
        )

    if costo_envio > 0:
        descripcion_historial += (
            f' - Costo adicional S/ {costo_envio:,.2f}'
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
            descripcion_historial,
            now
        )
    )

    c.commit()

    # ==========================================
    # USUARIOS DE OBRA A NOTIFICAR
    # ==========================================

    destinatarios_obra = c.execute(
        '''
        SELECT id
        FROM users
        WHERE role IN ('Gerencia de Obra','Control y Planeamiento') AND active=1
        '''
    ).fetchall()

    tracking = t['tracking']

    c.close()

    # ==========================================
    # NOTIFICACIONES
    # ==========================================

    for destinatario in destinatarios_obra:

        notify(
            destinatario['id'],
            tid,
            'Requerimiento enviado a obra',
            (
                f'El requerimiento {tracking} '
                f'fue enviado a obra mediante '
                f'{medio_envio}.'
            ),
            'accion'
        )

    flash(
        'Despacho registrado correctamente. '
        'El requerimiento fue enviado a obra.',
        'success'
    )

    return redirect(
        url_for(
            'request_detail',
            tid=tid
        )
    )


# =========================================================
# REEMBOLSOS / RENDICIONES
# =========================================================
REEMBURSEMENT_ROLES = ('Gerencia de Obra', 'Control y Planeamiento',
                       'Administración', 'Logística', 'Tesorería', 'Contabilidad', 'Sistemas')


@app.route('/reimbursements')
@login_required
def reimbursements():
    u = user()
    obra = active_project()
    c = db()
    rows = c.execute(
        '''SELECT r.*,u.full_name solicitante_nombre FROM reembolsos_rendiciones r JOIN users u ON u.id=r.solicitante_id WHERE r.obra_id=? ORDER BY r.id DESC''', (obra['id'],)).fetchall()
    files = c.execute(
        '''SELECT a.* FROM reembolso_adjuntos a JOIN reembolsos_rendiciones r ON r.id=a.reembolso_id WHERE r.obra_id=? ORDER BY a.id''', (obra['id'],)).fetchall()
    c.close()
    return render_template('reimbursements.html', user=u, rows=rows, rr_files=files)


@app.route('/reimbursements/new', methods=['GET', 'POST'])
@login_required
def new_reimbursement():
    u = user()
    obra = active_project()
    if u['role'] not in REEMBURSEMENT_ROLES:
        abort(403)
    if request.method == 'GET':
        return render_template('new_reimbursement.html', user=u, obra=obra)
    tipo = request.form.get('tipo', '')
    fecha = request.form.get('fecha', '')
    concepto = request.form.get('concepto', '').strip()
    moneda = request.form.get('moneda', 'PEN')
    obs = request.form.get('observaciones', '').strip()
    try:
        monto = parse_decimal(request.form.get('monto', '0'))
    except:
        monto = 0
    if tipo not in ('Reembolso', 'Rendición') or not fecha or not concepto or monto <= 0:
        flash('Complete tipo, fecha, concepto y monto.', 'danger')
        return redirect(url_for('new_reimbursement'))
    c = db()
    seq = c.execute('SELECT COALESCE(MAX(id),0)+1 n FROM reembolsos_rendiciones WHERE obra_id=?',
                    (obra['id'],)).fetchone()['n']
    numero = f'RR-{fecha[:4]}-{seq:03d}'
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    cur = c.execute('''INSERT INTO reembolsos_rendiciones(obra_id,tipo,numero,fecha,solicitante_id,concepto,monto,moneda,observaciones,estado,creado) VALUES(?,?,?,?,?,?,?,?,?,'Pendiente de Administración',?)''',
                    (obra['id'], tipo, numero, fecha, u['id'], concepto, monto, moneda, obs, now))
    rid = cur.lastrowid
    for f in request.files.getlist('documentos'):
        if f and f.filename:
            guard = secure_filename(
                f'RR_{rid}_{uuid.uuid4().hex[:8]}_{f.filename}')
            f.save(os.path.join(UPLOADS, guard))
            c.execute('INSERT INTO reembolso_adjuntos(reembolso_id,nombre_original,nombre_archivo,creado) VALUES(?,?,?,?)',
                      (rid, f.filename, guard, now))
    c.commit()
    c.close()
    flash(f'{tipo} registrado.', 'success')
    return redirect(url_for('reimbursements'))


@app.route('/reimbursements/<int:rid>/authorize', methods=['POST'])
@login_required
def authorize_reimbursement(rid):
    u = user()
    if u['role'] != 'Administración':
        abort(403)
    c = db()
    r = c.execute('SELECT * FROM reembolsos_rendiciones WHERE id=? AND obra_id=?',
                  (rid, active_project()['id'])).fetchone()
    if not r or r['estado'] != 'Pendiente de Administración':
        c.close()
        abort(400)
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    c.execute("UPDATE reembolsos_rendiciones SET estado='Pendiente de Tesorería',autorizado_por=?,fecha_autorizacion=? WHERE id=?",
              (u['id'], now, rid))
    c.commit()
    c.close()
    flash('Autorizado y enviado a Tesorería.', 'success')
    return redirect(url_for('reimbursements'))


@app.route('/reimbursements/<int:rid>/attend', methods=['POST'])
@login_required
def attend_reimbursement(rid):
    u = user()
    if u['role'] != 'Tesorería':
        abort(403)
    evidencia = request.files.get('evidencia')
    if not evidencia or not evidencia.filename:
        flash('Adjunte evidencia de atención.', 'danger')
        return redirect(url_for('reimbursements'))
    c = db()
    r = c.execute('SELECT * FROM reembolsos_rendiciones WHERE id=? AND obra_id=?',
                  (rid, active_project()['id'])).fetchone()
    if not r or r['estado'] != 'Pendiente de Tesorería':
        c.close()
        abort(400)
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    guard = secure_filename(
        f'RR_PAGO_{rid}_{uuid.uuid4().hex[:8]}_{evidencia.filename}')
    evidencia.save(os.path.join(UPLOADS, guard))
    c.execute('INSERT INTO reembolso_adjuntos(reembolso_id,tipo,nombre_original,nombre_archivo,creado) VALUES(?,?,?,?,?)',
              (rid, 'Evidencia de atención', evidencia.filename, guard, now))
    c.execute("UPDATE reembolsos_rendiciones SET estado='Atendido',atendido_por=?,fecha_atencion=? WHERE id=?",
              (u['id'], now, rid))
    c.commit()
    c.close()
    flash('Atención registrada.', 'success')
    return redirect(url_for('reimbursements'))


@app.route('/reimbursements/files/<int:fid>')
@login_required
def reimbursement_file(fid):
    c = db()
    f = c.execute('''SELECT a.* FROM reembolso_adjuntos a JOIN reembolsos_rendiciones r ON r.id=a.reembolso_id WHERE a.id=? AND r.obra_id=?''',
                  (fid, active_project()['id'])).fetchone()
    c.close()
    if not f:
        abort(404)
    return send_from_directory(UPLOADS, f['nombre_archivo'], as_attachment=False, download_name=f['nombre_original'])

# =========================================================
# ÓRDENES (registro base; formato oficial pendiente)
# =========================================================


@app.route('/orders')
@login_required
def orders():
    u = user()
    obra = active_project()
    c = db()
    rows = c.execute(
        '''SELECT o.*,u.full_name creador_nombre FROM ordenes o JOIN users u ON u.id=o.creador WHERE o.obra_id=? ORDER BY o.id DESC''', (obra['id'],)).fetchall()
    files = c.execute(
        '''SELECT a.* FROM orden_adjuntos a JOIN ordenes o ON o.id=a.orden_id WHERE o.obra_id=? ORDER BY a.id''', (obra['id'],)).fetchall()
    c.close()
    return render_template('orders.html', user=u, rows=rows, order_files=files)


@app.route('/orders/new', methods=['GET', 'POST'])
@login_required
def new_order():
    u = user()
    obra = active_project()
    if u['role'] not in ('Gerencia de Obra', 'Administración', 'Sistemas'):
        abort(403)
    if request.method == 'GET':
        c = db()
        prov = c.execute(
            'SELECT * FROM proveedores WHERE active=1 ORDER BY nombre').fetchall()
        req_units, _ = get_active_units(c)
        c.close()
        return render_template('new_order.html', user=u, obra=obra, providers=prov, units=req_units)
    tipo = request.form.get('tipo_orden', '')
    numero = request.form.get('numero', '').strip()
    fecha = request.form.get('fecha', '')
    pname = request.form.get('proveedor_nombre', '').strip()
    doc = request.form.get('documento', '').strip()
    moneda = request.form.get('moneda', 'PEN')
    desc = request.form.get('descripcion', '').strip()
    if tipo not in ('Orden de compra', 'Orden de servicio') or not numero or not fecha or not pname:
        flash('Complete tipo, número, fecha y proveedor.', 'danger')
        return redirect(url_for('new_order'))
    conceptos = request.form.getlist('descripcion_item[]')
    unds = request.form.getlist('unidad_item[]')
    qtys = request.form.getlist('cantidad_item[]')
    prices = request.form.getlist('precio_item[]')
    its = []
    for i, x in enumerate(conceptos):
        if not x.strip():
            continue
        q = parse_decimal(qtys[i])
        p = parse_decimal(prices[i])
        its.append((x.strip(), unds[i], q, p, q*p))
    if not its:
        flash('Agregue al menos un detalle.', 'danger')
        return redirect(url_for('new_order'))
    c = db()
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    total = sum(x[4] for x in its)
    cur = c.execute('''INSERT INTO ordenes(obra_id,tipo_orden,numero,fecha,proveedor_nombre,documento,moneda,descripcion,total,estado,creador,creado) VALUES(?,?,?,?,?,?,?,?,?,'Pendiente de Gerencia de Obra',?,?)''',
                    (obra['id'], tipo, numero, fecha, pname, doc, moneda, desc, total, u['id'], now))
    oid = cur.lastrowid
    for n, x in enumerate(its, 1):
        c.execute('INSERT INTO orden_items(orden_id,nro,descripcion,unidad,cantidad,precio_unitario,monto) VALUES(?,?,?,?,?,?,?)', (oid, n, *x))
    for f in request.files.getlist('documentos'):
        if f and f.filename:
            guard = secure_filename(
                f'ORD_{oid}_{uuid.uuid4().hex[:8]}_{f.filename}')
            f.save(os.path.join(UPLOADS, guard))
            c.execute('INSERT INTO orden_adjuntos(orden_id,nombre_original,nombre_archivo,creado) VALUES(?,?,?,?)',
                      (oid, f.filename, guard, now))
    c.commit()
    c.close()
    flash('Orden registrada. El formato oficial de exportación queda pendiente del modelo LANR.', 'success')
    return redirect(url_for('orders'))


@app.route('/orders/files/<int:fid>')
@login_required
def order_file(fid):
    c = db()
    f = c.execute("SELECT a.* FROM orden_adjuntos a JOIN ordenes o ON o.id=a.orden_id WHERE a.id=? AND o.obra_id=?",
                  (fid, active_project()['id'])).fetchone()
    c.close()
    if not f:
        abort(404)
    return send_from_directory(UPLOADS, f['nombre_archivo'], as_attachment=False, download_name=f['nombre_original'])


@app.route('/orders/<int:oid>/approve', methods=['POST'])
@login_required
def approve_order(oid):
    u = user()
    obra = active_project()
    c = db()
    o = c.execute('SELECT * FROM ordenes WHERE id=? AND obra_id=?',
                  (oid, obra['id'])).fetchone()
    if not o:
        c.close()
        abort(404)
    now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    if o['estado'] == 'Pendiente de Gerencia de Obra' and u['role'] == 'Gerencia de Obra':
        c.execute(
            "UPDATE ordenes SET estado='Pendiente de Administración',vb_gerencia_obra_por=?,vb_gerencia_obra_fecha=? WHERE id=?", (u['id'], now, oid))
        msg = 'V°B° de Gerencia de Obra registrado.'
    elif o['estado'] == 'Pendiente de Administración' and u['role'] == 'Administración':
        c.execute(
            "UPDATE ordenes SET estado='Pendiente de Gerencia General',vb_administracion_por=?,vb_administracion_fecha=? WHERE id=?", (u['id'], now, oid))
        msg = 'V°B° de Administración registrado.'
    elif o['estado'] == 'Pendiente de Gerencia General' and u['role'] == 'Gerencia General':
        c.execute(
            "UPDATE ordenes SET estado='Aprobada',vb_gerencia_general_por=?,vb_gerencia_general_fecha=? WHERE id=?", (u['id'], now, oid))
        msg = 'Orden aprobada por Gerencia General.'
    else:
        c.close()
        abort(403)
    c.commit()
    c.close()
    flash(msg, 'success')
    return redirect(url_for('orders'))

# =========================================================
# MANEJO AMIGABLE DE ERRORES
# =========================================================


@app.errorhandler(404)
def pagina_no_encontrada(error):
    flash(
        'La página o el trámite solicitado no existe.',
        'warning'
    )
    return redirect(url_for('home'))


@app.errorhandler(403)
def acceso_denegado(error):
    flash(
        'No tienes permiso para acceder a esa sección.',
        'danger'
    )
    return redirect(url_for('home'))


if __name__ == '__main__':
    init_db()
    ensure_integral_schema()
    app.run(
        debug=True,
        use_reloader=True,
        host='127.0.0.1',
        port=5000
    )
