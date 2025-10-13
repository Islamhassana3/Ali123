<?php
namespace Ali123\Importer;

use DateTimeInterface;
use wpdb;
use function get_current_blog_id;
use function wp_parse_args;

/**
 * Persistence layer for the import queue.
 */
class Import_Queue_Store {
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FAILED     = 'failed';
    const STATUS_COMPLETED  = 'completed';

    /**
     * Maximum records returned by default.
     */
    const DEFAULT_LIMIT = 50;

    /**
     * Database table name.
     *
     * @var string
     */
    protected $table;

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    protected $wpdb;

    /**
     * Constructor.
     */
    public function __construct( ?wpdb $db = null ) {
        global $wpdb;

        $this->wpdb  = $db ?: $wpdb;
        $this->table = $this->wpdb->prefix . 'ali123_queue';
    }

    /**
     * Install database schema.
     */
    public function install() : void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->wpdb->get_charset_collate();
        $table           = $this->table;

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            scheduled_at DATETIME NOT NULL,
            payload LONGTEXT NOT NULL,
            last_error TEXT NULL,
            last_error_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY store_schedule (store_id, status, scheduled_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Queue a new payload.
     */
    public function add( array $payload, array $meta = [] ) : array {
        $now        = current_time( 'mysql', true );
        $scheduled  = isset( $meta['scheduled_at'] ) ? $this->normalize_datetime( $meta['scheduled_at'] ) : $now;
        $store_id   = isset( $meta['store_id'] ) ? (int) $meta['store_id'] : get_current_blog_id();
        $status     = $meta['status'] ?? self::STATUS_PENDING;

        $encoded_payload = wp_json_encode( $payload );
        if ( false === $encoded_payload ) {
            return [];
        }

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'store_id'     => $store_id,
                'status'       => $status,
                'attempts'     => 0,
                'scheduled_at' => $scheduled,
                'payload'      => $encoded_payload,
                'last_error'   => null,
                'last_error_at'=> null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserted ) {
            return [];
        }

        $id = (int) $this->wpdb->insert_id;

        return $this->get( $id );
    }

    /**
     * Update an existing entry.
     */
    public function update( int $id, array $data ) : ?array {
        $fields = [];
        $format = [];

        if ( isset( $data['status'] ) ) {
            $fields['status'] = $data['status'];
            $format[]         = '%s';
        }

        if ( isset( $data['scheduled_at'] ) ) {
            $fields['scheduled_at'] = $this->normalize_datetime( $data['scheduled_at'] );
            $format[]               = '%s';
        }

        if ( isset( $data['payload'] ) ) {
            $encoded_payload = wp_json_encode( $data['payload'] );
            if ( false === $encoded_payload ) {
                return null;
            }

            $fields['payload'] = $encoded_payload;
            $format[]          = '%s';
        }

        if ( array_key_exists( 'last_error', $data ) ) {
            $fields['last_error'] = $data['last_error'];
            $format[]             = '%s';
        }

        if ( isset( $data['last_error_at'] ) ) {
            $fields['last_error_at'] = $this->normalize_datetime( $data['last_error_at'] );
            $format[]                = '%s';
        }

        if ( empty( $fields ) ) {
            return $this->get( $id );
        }

        $fields['updated_at'] = current_time( 'mysql', true );
        $format[]             = '%s';

        $updated = $this->wpdb->update( $this->table, $fields, [ 'id' => $id ], $format, [ '%d' ] );

        if ( false === $updated ) {
            return null;
        }

        return $this->get( $id );
    }

    /**
     * Remove a queue entry.
     */
    public function delete( int $id ) : bool {
        return (bool) $this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Retrieve a single entry.
     */
    public function get( int $id ) : ?array {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return $this->hydrate_row( $row );
    }

    /**
     * Fetch entries with optional filters.
     */
    public function all( array $args = [] ) : array {
        $defaults = [
            'status'   => null,
            'store_id' => get_current_blog_id(),
            'limit'    => self::DEFAULT_LIMIT,
            'offset'   => 0,
        ];

        $args    = wp_parse_args( $args, $defaults );
        $clauses = [];
        $params  = [];

        if ( $args['store_id'] ) {
            $clauses[] = 'store_id = %d';
            $params[]  = (int) $args['store_id'];
        }

        if ( $args['status'] ) {
            if ( is_array( $args['status'] ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $args['status'] ), '%s' ) );
                $clauses[]    = "status IN ({$placeholders})";
                $params       = array_merge( $params, array_map( 'strval', $args['status'] ) );
            } else {
                $clauses[] = 'status = %s';
                $params[]  = (string) $args['status'];
            }
        }

        $where  = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
        $limit  = (int) $args['limit'];
        $offset = (int) $args['offset'];

        $sql     = "SELECT * FROM {$this->table} {$where} ORDER BY scheduled_at ASC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$params ), ARRAY_A );

        return array_map( [ $this, 'hydrate_row' ], $results );
    }

    /**
     * Claim a batch of due jobs for processing.
     */
    public function claim_due( int $limit = self::DEFAULT_LIMIT, ?int $store_id = null ) : array {
        $now        = current_time( 'mysql', true );
        $conditions = [ 'status = %s', 'scheduled_at <= %s' ];
        $params     = [ self::STATUS_PENDING, $now ];

        if ( null !== $store_id ) {
            $conditions[] = 'store_id = %d';
            $params[]     = $store_id;
        }

        $params[] = $limit;

        $where      = implode( ' AND ', $conditions );
        $candidates = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$where} ORDER BY scheduled_at ASC LIMIT %d",
                ...$params
            ),
            ARRAY_A
        );

        if ( empty( $candidates ) ) {
            return [];
        }

        $claimed = [];
        foreach ( $candidates as $row ) {
            $where = [ 'id' => (int) $row['id'], 'status' => self::STATUS_PENDING ];
            if ( null !== $store_id ) {
                $where['store_id'] = $store_id;
            }

            $where_format = null !== $store_id ? [ '%d', '%s', '%d' ] : [ '%d', '%s' ];

            $updated = $this->wpdb->update(
                $this->table,
                [
                    'status'     => self::STATUS_PROCESSING,
                    'attempts'   => (int) $row['attempts'] + 1,
                    'updated_at' => current_time( 'mysql', true ),
                ],
                $where,
                [ '%s', '%d', '%s' ],
                $where_format
            );

            if ( $updated ) {
                $row['status']   = self::STATUS_PROCESSING;
                $row['attempts'] = (int) $row['attempts'] + 1;
                $claimed[]       = $this->hydrate_row( $row );
            }
        }

        return $claimed;
    }

    /**
     * Mark job as failed.
     */
    public function mark_failed( int $id, string $message ) : void {
        $now = current_time( 'mysql', true );

        $this->wpdb->update(
            $this->table,
            [
                'status'       => self::STATUS_FAILED,
                'last_error'   => $message,
                'last_error_at'=> $now,
                'updated_at'   => $now,
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Mark job as completed.
     */
    public function mark_completed( int $id ) : void {
        $this->wpdb->update(
            $this->table,
            [
                'status'     => self::STATUS_COMPLETED,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Hydrate database row into structured array.
     */
    protected function hydrate_row( array $row ) : array {
        $row['id']       = (int) $row['id'];
        $row['store_id'] = (int) $row['store_id'];
        $row['attempts'] = (int) $row['attempts'];
        $row['payload']  = $row['payload'] ? json_decode( $row['payload'], true ) : [];
        if ( is_array( $row['payload'] ) ) {
            $row['payload']['id'] = $row['id'];
        }

        return $row;
    }

    /**
     * Normalize datetime values.
     */
    protected function normalize_datetime( $value ) : string {
        if ( $value instanceof DateTimeInterface ) {
            return $value->format( 'Y-m-d H:i:s' );
        }

        if ( is_numeric( $value ) ) {
            return gmdate( 'Y-m-d H:i:s', (int) $value );
        }

        if ( is_string( $value ) ) {
            $time = strtotime( $value );
            if ( false !== $time ) {
                return gmdate( 'Y-m-d H:i:s', $time );
            }
        }

        return current_time( 'mysql', true );
    }
}
