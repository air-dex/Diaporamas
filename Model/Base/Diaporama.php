<?php
/*************************************************************************************/
/*      This file is part of the "Diaporamas" Thelia 2 module.                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Diaporamas\Model\Base;

use \DateTime;
use \Exception;
use \PDO;
use Diaporamas\Model\Diaporama as ChildDiaporama;
use Diaporamas\Model\DiaporamaI18n as ChildDiaporamaI18n;
use Diaporamas\Model\DiaporamaI18nQuery as ChildDiaporamaI18nQuery;
use Diaporamas\Model\DiaporamaImage as ChildDiaporamaImage;
use Diaporamas\Model\DiaporamaImageQuery as ChildDiaporamaImageQuery;
use Diaporamas\Model\DiaporamaQuery as ChildDiaporamaQuery;
use Diaporamas\Model\DiaporamaVersion as ChildDiaporamaVersion;
use Diaporamas\Model\DiaporamaVersionQuery as ChildDiaporamaVersionQuery;
use Diaporamas\Model\Map\DiaporamaTableMap;
use Diaporamas\Model\Map\DiaporamaVersionTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;

abstract class Diaporama implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Diaporamas\\Model\\Map\\DiaporamaTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the id field.
     * @var        int
     */
    protected $id;

    /**
     * The value for the shortcode field.
     * @var        string
     */
    protected $shortcode;

    /**
     * The value for the created_at field.
     * @var        string
     */
    protected $created_at;

    /**
     * The value for the updated_at field.
     * @var        string
     */
    protected $updated_at;

    /**
     * The value for the version field.
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $version;

    /**
     * The value for the version_created_at field.
     * @var        string
     */
    protected $version_created_at;

    /**
     * The value for the version_created_by field.
     * @var        string
     */
    protected $version_created_by;

    /**
     * @var        ObjectCollection|ChildDiaporamaImage[] Collection to store aggregation of ChildDiaporamaImage objects.
     */
    protected $collDiaporamaImages;
    protected $collDiaporamaImagesPartial;

    /**
     * @var        ObjectCollection|ChildDiaporamaI18n[] Collection to store aggregation of ChildDiaporamaI18n objects.
     */
    protected $collDiaporamaI18ns;
    protected $collDiaporamaI18nsPartial;

    /**
     * @var        ObjectCollection|ChildDiaporamaVersion[] Collection to store aggregation of ChildDiaporamaVersion objects.
     */
    protected $collDiaporamaVersions;
    protected $collDiaporamaVersionsPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    // i18n behavior

    /**
     * Current locale
     * @var        string
     */
    protected $currentLocale = 'en_US';

    /**
     * Current translation objects
     * @var        array[ChildDiaporamaI18n]
     */
    protected $currentTranslations;

    // versionable behavior


    /**
     * @var bool
     */
    protected $enforceVersion = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $diaporamaImagesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $diaporamaI18nsScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection
     */
    protected $diaporamaVersionsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->version = 0;
    }

    /**
     * Initializes internal state of Diaporamas\Model\Base\Diaporama object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !!$this->modifiedColumns;
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return $this->modifiedColumns && isset($this->modifiedColumns[$col]);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns ? array_keys($this->modifiedColumns) : [];
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (Boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (Boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            if (isset($this->modifiedColumns[$col])) {
                unset($this->modifiedColumns[$col]);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>Diaporama</code> instance.  If
     * <code>obj</code> is an instance of <code>Diaporama</code>, delegates to
     * <code>equals(Diaporama)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        $thisclazz = get_class($this);
        if (!is_object($obj) || !($obj instanceof $thisclazz)) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey()
            || null === $obj->getPrimaryKey())  {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        if (null !== $this->getPrimaryKey()) {
            return crc32(serialize($this->getPrimaryKey()));
        }

        return crc32(serialize(clone $this));
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return Diaporama The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     *
     * @return Diaporama The current object, for fluid interface
     */
    public function importFrom($parser, $data)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), TableMap::TYPE_PHPNAME);

        return $this;
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        return array_keys(get_object_vars($this));
    }

    /**
     * Get the [id] column value.
     *
     * @return   int
     */
    public function getId()
    {

        return $this->id;
    }

    /**
     * Get the [shortcode] column value.
     *
     * @return   string
     */
    public function getShortcode()
    {

        return $this->shortcode;
    }

    /**
     * Get the [optionally formatted] temporal [created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->created_at;
        } else {
            return $this->created_at instanceof \DateTime ? $this->created_at->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [updated_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getUpdatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->updated_at;
        } else {
            return $this->updated_at instanceof \DateTime ? $this->updated_at->format($format) : null;
        }
    }

    /**
     * Get the [version] column value.
     *
     * @return   int
     */
    public function getVersion()
    {

        return $this->version;
    }

    /**
     * Get the [optionally formatted] temporal [version_created_at] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw \DateTime object will be returned.
     *
     * @return mixed Formatted date/time value as string or \DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getVersionCreatedAt($format = NULL)
    {
        if ($format === null) {
            return $this->version_created_at;
        } else {
            return $this->version_created_at instanceof \DateTime ? $this->version_created_at->format($format) : null;
        }
    }

    /**
     * Get the [version_created_by] column value.
     *
     * @return   string
     */
    public function getVersionCreatedBy()
    {

        return $this->version_created_by;
    }

    /**
     * Set the value of [id] column.
     *
     * @param      int $v new value
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->id !== $v) {
            $this->id = $v;
            $this->modifiedColumns[DiaporamaTableMap::ID] = true;
        }


        return $this;
    } // setId()

    /**
     * Set the value of [shortcode] column.
     *
     * @param      string $v new value
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setShortcode($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->shortcode !== $v) {
            $this->shortcode = $v;
            $this->modifiedColumns[DiaporamaTableMap::SHORTCODE] = true;
        }


        return $this;
    } // setShortcode()

    /**
     * Sets the value of [created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->created_at !== null || $dt !== null) {
            if ($dt !== $this->created_at) {
                $this->created_at = $dt;
                $this->modifiedColumns[DiaporamaTableMap::CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setCreatedAt()

    /**
     * Sets the value of [updated_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setUpdatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->updated_at !== null || $dt !== null) {
            if ($dt !== $this->updated_at) {
                $this->updated_at = $dt;
                $this->modifiedColumns[DiaporamaTableMap::UPDATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setUpdatedAt()

    /**
     * Set the value of [version] column.
     *
     * @param      int $v new value
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setVersion($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->version !== $v) {
            $this->version = $v;
            $this->modifiedColumns[DiaporamaTableMap::VERSION] = true;
        }


        return $this;
    } // setVersion()

    /**
     * Sets the value of [version_created_at] column to a normalized version of the date/time value specified.
     *
     * @param      mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setVersionCreatedAt($v)
    {
        $dt = PropelDateTime::newInstance($v, null, '\DateTime');
        if ($this->version_created_at !== null || $dt !== null) {
            if ($dt !== $this->version_created_at) {
                $this->version_created_at = $dt;
                $this->modifiedColumns[DiaporamaTableMap::VERSION_CREATED_AT] = true;
            }
        } // if either are not null


        return $this;
    } // setVersionCreatedAt()

    /**
     * Set the value of [version_created_by] column.
     *
     * @param      string $v new value
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function setVersionCreatedBy($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->version_created_by !== $v) {
            $this->version_created_by = $v;
            $this->modifiedColumns[DiaporamaTableMap::VERSION_CREATED_BY] = true;
        }


        return $this;
    } // setVersionCreatedBy()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
            if ($this->version !== 0) {
                return false;
            }

        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {


            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : DiaporamaTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : DiaporamaTableMap::translateFieldName('Shortcode', TableMap::TYPE_PHPNAME, $indexType)];
            $this->shortcode = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : DiaporamaTableMap::translateFieldName('CreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : DiaporamaTableMap::translateFieldName('UpdatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->updated_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : DiaporamaTableMap::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : DiaporamaTableMap::translateFieldName('VersionCreatedAt', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->version_created_at = (null !== $col) ? PropelDateTime::newInstance($col, null, '\DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : DiaporamaTableMap::translateFieldName('VersionCreatedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->version_created_by = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 7; // 7 = DiaporamaTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException("Error populating \Diaporamas\Model\Diaporama object", 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(DiaporamaTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildDiaporamaQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collDiaporamaImages = null;

            $this->collDiaporamaI18ns = null;

            $this->collDiaporamaVersions = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Diaporama::setDeleted()
     * @see Diaporama::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(DiaporamaTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        try {
            $deleteQuery = ChildDiaporamaQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $con->commit();
                $this->setDeleted(true);
            } else {
                $con->commit();
            }
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(DiaporamaTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();
        $isInsert = $this->isNew();
        try {
            $ret = $this->preSave($con);
            // versionable behavior
            if ($this->isVersioningNecessary()) {
                $this->setVersion($this->isNew() ? 1 : $this->getLastVersionNumber($con) + 1);
                if (!$this->isColumnModified(DiaporamaTableMap::VERSION_CREATED_AT)) {
                    $this->setVersionCreatedAt(time());
                }
                $createVersion = true; // for postSave hook
            }
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
                // timestampable behavior
                if (!$this->isColumnModified(DiaporamaTableMap::CREATED_AT)) {
                    $this->setCreatedAt(time());
                }
                if (!$this->isColumnModified(DiaporamaTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            } else {
                $ret = $ret && $this->preUpdate($con);
                // timestampable behavior
                if ($this->isModified() && !$this->isColumnModified(DiaporamaTableMap::UPDATED_AT)) {
                    $this->setUpdatedAt(time());
                }
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                // versionable behavior
                if (isset($createVersion)) {
                    $this->addVersion($con);
                }
                DiaporamaTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }
            $con->commit();

            return $affectedRows;
        } catch (Exception $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                } else {
                    $this->doUpdate($con);
                }
                $affectedRows += 1;
                $this->resetModified();
            }

            if ($this->diaporamaImagesScheduledForDeletion !== null) {
                if (!$this->diaporamaImagesScheduledForDeletion->isEmpty()) {
                    \Diaporamas\Model\DiaporamaImageQuery::create()
                        ->filterByPrimaryKeys($this->diaporamaImagesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->diaporamaImagesScheduledForDeletion = null;
                }
            }

                if ($this->collDiaporamaImages !== null) {
            foreach ($this->collDiaporamaImages as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->diaporamaI18nsScheduledForDeletion !== null) {
                if (!$this->diaporamaI18nsScheduledForDeletion->isEmpty()) {
                    \Diaporamas\Model\DiaporamaI18nQuery::create()
                        ->filterByPrimaryKeys($this->diaporamaI18nsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->diaporamaI18nsScheduledForDeletion = null;
                }
            }

                if ($this->collDiaporamaI18ns !== null) {
            foreach ($this->collDiaporamaI18ns as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->diaporamaVersionsScheduledForDeletion !== null) {
                if (!$this->diaporamaVersionsScheduledForDeletion->isEmpty()) {
                    \Diaporamas\Model\DiaporamaVersionQuery::create()
                        ->filterByPrimaryKeys($this->diaporamaVersionsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->diaporamaVersionsScheduledForDeletion = null;
                }
            }

                if ($this->collDiaporamaVersions !== null) {
            foreach ($this->collDiaporamaVersions as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[DiaporamaTableMap::ID] = true;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . DiaporamaTableMap::ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(DiaporamaTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = 'ID';
        }
        if ($this->isColumnModified(DiaporamaTableMap::SHORTCODE)) {
            $modifiedColumns[':p' . $index++]  = 'SHORTCODE';
        }
        if ($this->isColumnModified(DiaporamaTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'CREATED_AT';
        }
        if ($this->isColumnModified(DiaporamaTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'UPDATED_AT';
        }
        if ($this->isColumnModified(DiaporamaTableMap::VERSION)) {
            $modifiedColumns[':p' . $index++]  = 'VERSION';
        }
        if ($this->isColumnModified(DiaporamaTableMap::VERSION_CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'VERSION_CREATED_AT';
        }
        if ($this->isColumnModified(DiaporamaTableMap::VERSION_CREATED_BY)) {
            $modifiedColumns[':p' . $index++]  = 'VERSION_CREATED_BY';
        }

        $sql = sprintf(
            'INSERT INTO diaporama (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'ID':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'SHORTCODE':
                        $stmt->bindValue($identifier, $this->shortcode, PDO::PARAM_STR);
                        break;
                    case 'CREATED_AT':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'UPDATED_AT':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'VERSION':
                        $stmt->bindValue($identifier, $this->version, PDO::PARAM_INT);
                        break;
                    case 'VERSION_CREATED_AT':
                        $stmt->bindValue($identifier, $this->version_created_at ? $this->version_created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'VERSION_CREATED_BY':
                        $stmt->bindValue($identifier, $this->version_created_by, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = DiaporamaTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getId();
                break;
            case 1:
                return $this->getShortcode();
                break;
            case 2:
                return $this->getCreatedAt();
                break;
            case 3:
                return $this->getUpdatedAt();
                break;
            case 4:
                return $this->getVersion();
                break;
            case 5:
                return $this->getVersionCreatedAt();
                break;
            case 6:
                return $this->getVersionCreatedBy();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        if (isset($alreadyDumpedObjects['Diaporama'][$this->getPrimaryKey()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Diaporama'][$this->getPrimaryKey()] = true;
        $keys = DiaporamaTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getShortcode(),
            $keys[2] => $this->getCreatedAt(),
            $keys[3] => $this->getUpdatedAt(),
            $keys[4] => $this->getVersion(),
            $keys[5] => $this->getVersionCreatedAt(),
            $keys[6] => $this->getVersionCreatedBy(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collDiaporamaImages) {
                $result['DiaporamaImages'] = $this->collDiaporamaImages->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collDiaporamaI18ns) {
                $result['DiaporamaI18ns'] = $this->collDiaporamaI18ns->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collDiaporamaVersions) {
                $result['DiaporamaVersions'] = $this->collDiaporamaVersions->toArray(null, true, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param      string $name
     * @param      mixed  $value field value
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return void
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = DiaporamaTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @param      mixed $value field value
     * @return void
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setShortcode($value);
                break;
            case 2:
                $this->setCreatedAt($value);
                break;
            case 3:
                $this->setUpdatedAt($value);
                break;
            case 4:
                $this->setVersion($value);
                break;
            case 5:
                $this->setVersionCreatedAt($value);
                break;
            case 6:
                $this->setVersionCreatedBy($value);
                break;
        } // switch()
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = DiaporamaTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) $this->setId($arr[$keys[0]]);
        if (array_key_exists($keys[1], $arr)) $this->setShortcode($arr[$keys[1]]);
        if (array_key_exists($keys[2], $arr)) $this->setCreatedAt($arr[$keys[2]]);
        if (array_key_exists($keys[3], $arr)) $this->setUpdatedAt($arr[$keys[3]]);
        if (array_key_exists($keys[4], $arr)) $this->setVersion($arr[$keys[4]]);
        if (array_key_exists($keys[5], $arr)) $this->setVersionCreatedAt($arr[$keys[5]]);
        if (array_key_exists($keys[6], $arr)) $this->setVersionCreatedBy($arr[$keys[6]]);
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(DiaporamaTableMap::DATABASE_NAME);

        if ($this->isColumnModified(DiaporamaTableMap::ID)) $criteria->add(DiaporamaTableMap::ID, $this->id);
        if ($this->isColumnModified(DiaporamaTableMap::SHORTCODE)) $criteria->add(DiaporamaTableMap::SHORTCODE, $this->shortcode);
        if ($this->isColumnModified(DiaporamaTableMap::CREATED_AT)) $criteria->add(DiaporamaTableMap::CREATED_AT, $this->created_at);
        if ($this->isColumnModified(DiaporamaTableMap::UPDATED_AT)) $criteria->add(DiaporamaTableMap::UPDATED_AT, $this->updated_at);
        if ($this->isColumnModified(DiaporamaTableMap::VERSION)) $criteria->add(DiaporamaTableMap::VERSION, $this->version);
        if ($this->isColumnModified(DiaporamaTableMap::VERSION_CREATED_AT)) $criteria->add(DiaporamaTableMap::VERSION_CREATED_AT, $this->version_created_at);
        if ($this->isColumnModified(DiaporamaTableMap::VERSION_CREATED_BY)) $criteria->add(DiaporamaTableMap::VERSION_CREATED_BY, $this->version_created_by);

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = new Criteria(DiaporamaTableMap::DATABASE_NAME);
        $criteria->add(DiaporamaTableMap::ID, $this->id);

        return $criteria;
    }

    /**
     * Returns the primary key for this object (row).
     * @return   int
     */
    public function getPrimaryKey()
    {
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {

        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \Diaporamas\Model\Diaporama (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setShortcode($this->getShortcode());
        $copyObj->setCreatedAt($this->getCreatedAt());
        $copyObj->setUpdatedAt($this->getUpdatedAt());
        $copyObj->setVersion($this->getVersion());
        $copyObj->setVersionCreatedAt($this->getVersionCreatedAt());
        $copyObj->setVersionCreatedBy($this->getVersionCreatedBy());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getDiaporamaImages() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addDiaporamaImage($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getDiaporamaI18ns() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addDiaporamaI18n($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getDiaporamaVersions() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addDiaporamaVersion($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return                 \Diaporamas\Model\Diaporama Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('DiaporamaImage' == $relationName) {
            return $this->initDiaporamaImages();
        }
        if ('DiaporamaI18n' == $relationName) {
            return $this->initDiaporamaI18ns();
        }
        if ('DiaporamaVersion' == $relationName) {
            return $this->initDiaporamaVersions();
        }
    }

    /**
     * Clears out the collDiaporamaImages collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addDiaporamaImages()
     */
    public function clearDiaporamaImages()
    {
        $this->collDiaporamaImages = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collDiaporamaImages collection loaded partially.
     */
    public function resetPartialDiaporamaImages($v = true)
    {
        $this->collDiaporamaImagesPartial = $v;
    }

    /**
     * Initializes the collDiaporamaImages collection.
     *
     * By default this just sets the collDiaporamaImages collection to an empty array (like clearcollDiaporamaImages());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initDiaporamaImages($overrideExisting = true)
    {
        if (null !== $this->collDiaporamaImages && !$overrideExisting) {
            return;
        }
        $this->collDiaporamaImages = new ObjectCollection();
        $this->collDiaporamaImages->setModel('\Diaporamas\Model\DiaporamaImage');
    }

    /**
     * Gets an array of ChildDiaporamaImage objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildDiaporama is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildDiaporamaImage[] List of ChildDiaporamaImage objects
     * @throws PropelException
     */
    public function getDiaporamaImages($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collDiaporamaImagesPartial && !$this->isNew();
        if (null === $this->collDiaporamaImages || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collDiaporamaImages) {
                // return empty collection
                $this->initDiaporamaImages();
            } else {
                $collDiaporamaImages = ChildDiaporamaImageQuery::create(null, $criteria)
                    ->filterByDiaporama($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collDiaporamaImagesPartial && count($collDiaporamaImages)) {
                        $this->initDiaporamaImages(false);

                        foreach ($collDiaporamaImages as $obj) {
                            if (false == $this->collDiaporamaImages->contains($obj)) {
                                $this->collDiaporamaImages->append($obj);
                            }
                        }

                        $this->collDiaporamaImagesPartial = true;
                    }

                    reset($collDiaporamaImages);

                    return $collDiaporamaImages;
                }

                if ($partial && $this->collDiaporamaImages) {
                    foreach ($this->collDiaporamaImages as $obj) {
                        if ($obj->isNew()) {
                            $collDiaporamaImages[] = $obj;
                        }
                    }
                }

                $this->collDiaporamaImages = $collDiaporamaImages;
                $this->collDiaporamaImagesPartial = false;
            }
        }

        return $this->collDiaporamaImages;
    }

    /**
     * Sets a collection of DiaporamaImage objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $diaporamaImages A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildDiaporama The current object (for fluent API support)
     */
    public function setDiaporamaImages(Collection $diaporamaImages, ConnectionInterface $con = null)
    {
        $diaporamaImagesToDelete = $this->getDiaporamaImages(new Criteria(), $con)->diff($diaporamaImages);


        $this->diaporamaImagesScheduledForDeletion = $diaporamaImagesToDelete;

        foreach ($diaporamaImagesToDelete as $diaporamaImageRemoved) {
            $diaporamaImageRemoved->setDiaporama(null);
        }

        $this->collDiaporamaImages = null;
        foreach ($diaporamaImages as $diaporamaImage) {
            $this->addDiaporamaImage($diaporamaImage);
        }

        $this->collDiaporamaImages = $diaporamaImages;
        $this->collDiaporamaImagesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related DiaporamaImage objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related DiaporamaImage objects.
     * @throws PropelException
     */
    public function countDiaporamaImages(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collDiaporamaImagesPartial && !$this->isNew();
        if (null === $this->collDiaporamaImages || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collDiaporamaImages) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getDiaporamaImages());
            }

            $query = ChildDiaporamaImageQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByDiaporama($this)
                ->count($con);
        }

        return count($this->collDiaporamaImages);
    }

    /**
     * Method called to associate a ChildDiaporamaImage object to this object
     * through the ChildDiaporamaImage foreign key attribute.
     *
     * @param    ChildDiaporamaImage $l ChildDiaporamaImage
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function addDiaporamaImage(ChildDiaporamaImage $l)
    {
        if ($this->collDiaporamaImages === null) {
            $this->initDiaporamaImages();
            $this->collDiaporamaImagesPartial = true;
        }

        if (!in_array($l, $this->collDiaporamaImages->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddDiaporamaImage($l);
        }

        return $this;
    }

    /**
     * @param DiaporamaImage $diaporamaImage The diaporamaImage object to add.
     */
    protected function doAddDiaporamaImage($diaporamaImage)
    {
        $this->collDiaporamaImages[]= $diaporamaImage;
        $diaporamaImage->setDiaporama($this);
    }

    /**
     * @param  DiaporamaImage $diaporamaImage The diaporamaImage object to remove.
     * @return ChildDiaporama The current object (for fluent API support)
     */
    public function removeDiaporamaImage($diaporamaImage)
    {
        if ($this->getDiaporamaImages()->contains($diaporamaImage)) {
            $this->collDiaporamaImages->remove($this->collDiaporamaImages->search($diaporamaImage));
            if (null === $this->diaporamaImagesScheduledForDeletion) {
                $this->diaporamaImagesScheduledForDeletion = clone $this->collDiaporamaImages;
                $this->diaporamaImagesScheduledForDeletion->clear();
            }
            $this->diaporamaImagesScheduledForDeletion[]= clone $diaporamaImage;
            $diaporamaImage->setDiaporama(null);
        }

        return $this;
    }

    /**
     * Clears out the collDiaporamaI18ns collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addDiaporamaI18ns()
     */
    public function clearDiaporamaI18ns()
    {
        $this->collDiaporamaI18ns = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collDiaporamaI18ns collection loaded partially.
     */
    public function resetPartialDiaporamaI18ns($v = true)
    {
        $this->collDiaporamaI18nsPartial = $v;
    }

    /**
     * Initializes the collDiaporamaI18ns collection.
     *
     * By default this just sets the collDiaporamaI18ns collection to an empty array (like clearcollDiaporamaI18ns());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initDiaporamaI18ns($overrideExisting = true)
    {
        if (null !== $this->collDiaporamaI18ns && !$overrideExisting) {
            return;
        }
        $this->collDiaporamaI18ns = new ObjectCollection();
        $this->collDiaporamaI18ns->setModel('\Diaporamas\Model\DiaporamaI18n');
    }

    /**
     * Gets an array of ChildDiaporamaI18n objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildDiaporama is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildDiaporamaI18n[] List of ChildDiaporamaI18n objects
     * @throws PropelException
     */
    public function getDiaporamaI18ns($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collDiaporamaI18nsPartial && !$this->isNew();
        if (null === $this->collDiaporamaI18ns || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collDiaporamaI18ns) {
                // return empty collection
                $this->initDiaporamaI18ns();
            } else {
                $collDiaporamaI18ns = ChildDiaporamaI18nQuery::create(null, $criteria)
                    ->filterByDiaporama($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collDiaporamaI18nsPartial && count($collDiaporamaI18ns)) {
                        $this->initDiaporamaI18ns(false);

                        foreach ($collDiaporamaI18ns as $obj) {
                            if (false == $this->collDiaporamaI18ns->contains($obj)) {
                                $this->collDiaporamaI18ns->append($obj);
                            }
                        }

                        $this->collDiaporamaI18nsPartial = true;
                    }

                    reset($collDiaporamaI18ns);

                    return $collDiaporamaI18ns;
                }

                if ($partial && $this->collDiaporamaI18ns) {
                    foreach ($this->collDiaporamaI18ns as $obj) {
                        if ($obj->isNew()) {
                            $collDiaporamaI18ns[] = $obj;
                        }
                    }
                }

                $this->collDiaporamaI18ns = $collDiaporamaI18ns;
                $this->collDiaporamaI18nsPartial = false;
            }
        }

        return $this->collDiaporamaI18ns;
    }

    /**
     * Sets a collection of DiaporamaI18n objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $diaporamaI18ns A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildDiaporama The current object (for fluent API support)
     */
    public function setDiaporamaI18ns(Collection $diaporamaI18ns, ConnectionInterface $con = null)
    {
        $diaporamaI18nsToDelete = $this->getDiaporamaI18ns(new Criteria(), $con)->diff($diaporamaI18ns);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->diaporamaI18nsScheduledForDeletion = clone $diaporamaI18nsToDelete;

        foreach ($diaporamaI18nsToDelete as $diaporamaI18nRemoved) {
            $diaporamaI18nRemoved->setDiaporama(null);
        }

        $this->collDiaporamaI18ns = null;
        foreach ($diaporamaI18ns as $diaporamaI18n) {
            $this->addDiaporamaI18n($diaporamaI18n);
        }

        $this->collDiaporamaI18ns = $diaporamaI18ns;
        $this->collDiaporamaI18nsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related DiaporamaI18n objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related DiaporamaI18n objects.
     * @throws PropelException
     */
    public function countDiaporamaI18ns(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collDiaporamaI18nsPartial && !$this->isNew();
        if (null === $this->collDiaporamaI18ns || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collDiaporamaI18ns) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getDiaporamaI18ns());
            }

            $query = ChildDiaporamaI18nQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByDiaporama($this)
                ->count($con);
        }

        return count($this->collDiaporamaI18ns);
    }

    /**
     * Method called to associate a ChildDiaporamaI18n object to this object
     * through the ChildDiaporamaI18n foreign key attribute.
     *
     * @param    ChildDiaporamaI18n $l ChildDiaporamaI18n
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function addDiaporamaI18n(ChildDiaporamaI18n $l)
    {
        if ($l && $locale = $l->getLocale()) {
            $this->setLocale($locale);
            $this->currentTranslations[$locale] = $l;
        }
        if ($this->collDiaporamaI18ns === null) {
            $this->initDiaporamaI18ns();
            $this->collDiaporamaI18nsPartial = true;
        }

        if (!in_array($l, $this->collDiaporamaI18ns->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddDiaporamaI18n($l);
        }

        return $this;
    }

    /**
     * @param DiaporamaI18n $diaporamaI18n The diaporamaI18n object to add.
     */
    protected function doAddDiaporamaI18n($diaporamaI18n)
    {
        $this->collDiaporamaI18ns[]= $diaporamaI18n;
        $diaporamaI18n->setDiaporama($this);
    }

    /**
     * @param  DiaporamaI18n $diaporamaI18n The diaporamaI18n object to remove.
     * @return ChildDiaporama The current object (for fluent API support)
     */
    public function removeDiaporamaI18n($diaporamaI18n)
    {
        if ($this->getDiaporamaI18ns()->contains($diaporamaI18n)) {
            $this->collDiaporamaI18ns->remove($this->collDiaporamaI18ns->search($diaporamaI18n));
            if (null === $this->diaporamaI18nsScheduledForDeletion) {
                $this->diaporamaI18nsScheduledForDeletion = clone $this->collDiaporamaI18ns;
                $this->diaporamaI18nsScheduledForDeletion->clear();
            }
            $this->diaporamaI18nsScheduledForDeletion[]= clone $diaporamaI18n;
            $diaporamaI18n->setDiaporama(null);
        }

        return $this;
    }

    /**
     * Clears out the collDiaporamaVersions collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addDiaporamaVersions()
     */
    public function clearDiaporamaVersions()
    {
        $this->collDiaporamaVersions = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collDiaporamaVersions collection loaded partially.
     */
    public function resetPartialDiaporamaVersions($v = true)
    {
        $this->collDiaporamaVersionsPartial = $v;
    }

    /**
     * Initializes the collDiaporamaVersions collection.
     *
     * By default this just sets the collDiaporamaVersions collection to an empty array (like clearcollDiaporamaVersions());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initDiaporamaVersions($overrideExisting = true)
    {
        if (null !== $this->collDiaporamaVersions && !$overrideExisting) {
            return;
        }
        $this->collDiaporamaVersions = new ObjectCollection();
        $this->collDiaporamaVersions->setModel('\Diaporamas\Model\DiaporamaVersion');
    }

    /**
     * Gets an array of ChildDiaporamaVersion objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildDiaporama is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return Collection|ChildDiaporamaVersion[] List of ChildDiaporamaVersion objects
     * @throws PropelException
     */
    public function getDiaporamaVersions($criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collDiaporamaVersionsPartial && !$this->isNew();
        if (null === $this->collDiaporamaVersions || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collDiaporamaVersions) {
                // return empty collection
                $this->initDiaporamaVersions();
            } else {
                $collDiaporamaVersions = ChildDiaporamaVersionQuery::create(null, $criteria)
                    ->filterByDiaporama($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collDiaporamaVersionsPartial && count($collDiaporamaVersions)) {
                        $this->initDiaporamaVersions(false);

                        foreach ($collDiaporamaVersions as $obj) {
                            if (false == $this->collDiaporamaVersions->contains($obj)) {
                                $this->collDiaporamaVersions->append($obj);
                            }
                        }

                        $this->collDiaporamaVersionsPartial = true;
                    }

                    reset($collDiaporamaVersions);

                    return $collDiaporamaVersions;
                }

                if ($partial && $this->collDiaporamaVersions) {
                    foreach ($this->collDiaporamaVersions as $obj) {
                        if ($obj->isNew()) {
                            $collDiaporamaVersions[] = $obj;
                        }
                    }
                }

                $this->collDiaporamaVersions = $collDiaporamaVersions;
                $this->collDiaporamaVersionsPartial = false;
            }
        }

        return $this->collDiaporamaVersions;
    }

    /**
     * Sets a collection of DiaporamaVersion objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $diaporamaVersions A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return   ChildDiaporama The current object (for fluent API support)
     */
    public function setDiaporamaVersions(Collection $diaporamaVersions, ConnectionInterface $con = null)
    {
        $diaporamaVersionsToDelete = $this->getDiaporamaVersions(new Criteria(), $con)->diff($diaporamaVersions);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->diaporamaVersionsScheduledForDeletion = clone $diaporamaVersionsToDelete;

        foreach ($diaporamaVersionsToDelete as $diaporamaVersionRemoved) {
            $diaporamaVersionRemoved->setDiaporama(null);
        }

        $this->collDiaporamaVersions = null;
        foreach ($diaporamaVersions as $diaporamaVersion) {
            $this->addDiaporamaVersion($diaporamaVersion);
        }

        $this->collDiaporamaVersions = $diaporamaVersions;
        $this->collDiaporamaVersionsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related DiaporamaVersion objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related DiaporamaVersion objects.
     * @throws PropelException
     */
    public function countDiaporamaVersions(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collDiaporamaVersionsPartial && !$this->isNew();
        if (null === $this->collDiaporamaVersions || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collDiaporamaVersions) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getDiaporamaVersions());
            }

            $query = ChildDiaporamaVersionQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByDiaporama($this)
                ->count($con);
        }

        return count($this->collDiaporamaVersions);
    }

    /**
     * Method called to associate a ChildDiaporamaVersion object to this object
     * through the ChildDiaporamaVersion foreign key attribute.
     *
     * @param    ChildDiaporamaVersion $l ChildDiaporamaVersion
     * @return   \Diaporamas\Model\Diaporama The current object (for fluent API support)
     */
    public function addDiaporamaVersion(ChildDiaporamaVersion $l)
    {
        if ($this->collDiaporamaVersions === null) {
            $this->initDiaporamaVersions();
            $this->collDiaporamaVersionsPartial = true;
        }

        if (!in_array($l, $this->collDiaporamaVersions->getArrayCopy(), true)) { // only add it if the **same** object is not already associated
            $this->doAddDiaporamaVersion($l);
        }

        return $this;
    }

    /**
     * @param DiaporamaVersion $diaporamaVersion The diaporamaVersion object to add.
     */
    protected function doAddDiaporamaVersion($diaporamaVersion)
    {
        $this->collDiaporamaVersions[]= $diaporamaVersion;
        $diaporamaVersion->setDiaporama($this);
    }

    /**
     * @param  DiaporamaVersion $diaporamaVersion The diaporamaVersion object to remove.
     * @return ChildDiaporama The current object (for fluent API support)
     */
    public function removeDiaporamaVersion($diaporamaVersion)
    {
        if ($this->getDiaporamaVersions()->contains($diaporamaVersion)) {
            $this->collDiaporamaVersions->remove($this->collDiaporamaVersions->search($diaporamaVersion));
            if (null === $this->diaporamaVersionsScheduledForDeletion) {
                $this->diaporamaVersionsScheduledForDeletion = clone $this->collDiaporamaVersions;
                $this->diaporamaVersionsScheduledForDeletion->clear();
            }
            $this->diaporamaVersionsScheduledForDeletion[]= clone $diaporamaVersion;
            $diaporamaVersion->setDiaporama(null);
        }

        return $this;
    }

    /**
     * Clears the current object and sets all attributes to their default values
     */
    public function clear()
    {
        $this->id = null;
        $this->shortcode = null;
        $this->created_at = null;
        $this->updated_at = null;
        $this->version = null;
        $this->version_created_at = null;
        $this->version_created_by = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->applyDefaultValues();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references to other model objects or collections of model objects.
     *
     * This method is a user-space workaround for PHP's inability to garbage collect
     * objects with circular references (even in PHP 5.3). This is currently necessary
     * when using Propel in certain daemon or large-volume/high-memory operations.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collDiaporamaImages) {
                foreach ($this->collDiaporamaImages as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collDiaporamaI18ns) {
                foreach ($this->collDiaporamaI18ns as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collDiaporamaVersions) {
                foreach ($this->collDiaporamaVersions as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        // i18n behavior
        $this->currentLocale = 'en_US';
        $this->currentTranslations = null;

        $this->collDiaporamaImages = null;
        $this->collDiaporamaI18ns = null;
        $this->collDiaporamaVersions = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(DiaporamaTableMap::DEFAULT_STRING_FORMAT);
    }

    // timestampable behavior

    /**
     * Mark the current object so that the update date doesn't get updated during next save
     *
     * @return     ChildDiaporama The current object (for fluent API support)
     */
    public function keepUpdateDateUnchanged()
    {
        $this->modifiedColumns[DiaporamaTableMap::UPDATED_AT] = true;

        return $this;
    }

    // i18n behavior

    /**
     * Sets the locale for translations
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     *
     * @return    ChildDiaporama The current object (for fluent API support)
     */
    public function setLocale($locale = 'en_US')
    {
        $this->currentLocale = $locale;

        return $this;
    }

    /**
     * Gets the locale for translations
     *
     * @return    string $locale Locale to use for the translation, e.g. 'fr_FR'
     */
    public function getLocale()
    {
        return $this->currentLocale;
    }

    /**
     * Returns the current translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildDiaporamaI18n */
    public function getTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            if (null !== $this->collDiaporamaI18ns) {
                foreach ($this->collDiaporamaI18ns as $translation) {
                    if ($translation->getLocale() == $locale) {
                        $this->currentTranslations[$locale] = $translation;

                        return $translation;
                    }
                }
            }
            if ($this->isNew()) {
                $translation = new ChildDiaporamaI18n();
                $translation->setLocale($locale);
            } else {
                $translation = ChildDiaporamaI18nQuery::create()
                    ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                    ->findOneOrCreate($con);
                $this->currentTranslations[$locale] = $translation;
            }
            $this->addDiaporamaI18n($translation);
        }

        return $this->currentTranslations[$locale];
    }

    /**
     * Remove the translation for a given locale
     *
     * @param     string $locale Locale to use for the translation, e.g. 'fr_FR'
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return    ChildDiaporama The current object (for fluent API support)
     */
    public function removeTranslation($locale = 'en_US', ConnectionInterface $con = null)
    {
        if (!$this->isNew()) {
            ChildDiaporamaI18nQuery::create()
                ->filterByPrimaryKey(array($this->getPrimaryKey(), $locale))
                ->delete($con);
        }
        if (isset($this->currentTranslations[$locale])) {
            unset($this->currentTranslations[$locale]);
        }
        foreach ($this->collDiaporamaI18ns as $key => $translation) {
            if ($translation->getLocale() == $locale) {
                unset($this->collDiaporamaI18ns[$key]);
                break;
            }
        }

        return $this;
    }

    /**
     * Returns the current translation
     *
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ChildDiaporamaI18n */
    public function getCurrentTranslation(ConnectionInterface $con = null)
    {
        return $this->getTranslation($this->getLocale(), $con);
    }


        /**
         * Get the [title] column value.
         *
         * @return   string
         */
        public function getTitle()
        {
        return $this->getCurrentTranslation()->getTitle();
    }


        /**
         * Set the value of [title] column.
         *
         * @param      string $v new value
         * @return   \Diaporamas\Model\DiaporamaI18n The current object (for fluent API support)
         */
        public function setTitle($v)
        {    $this->getCurrentTranslation()->setTitle($v);

        return $this;
    }

    // versionable behavior

    /**
     * Enforce a new Version of this object upon next save.
     *
     * @return \Diaporamas\Model\Diaporama
     */
    public function enforceVersioning()
    {
        $this->enforceVersion = true;

        return $this;
    }

    /**
     * Checks whether the current state must be recorded as a version
     *
     * @return  boolean
     */
    public function isVersioningNecessary($con = null)
    {
        if ($this->alreadyInSave) {
            return false;
        }

        if ($this->enforceVersion) {
            return true;
        }

        if (ChildDiaporamaQuery::isVersioningEnabled() && ($this->isNew() || $this->isModified()) || $this->isDeleted()) {
            return true;
        }

        return false;
    }

    /**
     * Creates a version of the current object and saves it.
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildDiaporamaVersion A version object
     */
    public function addVersion($con = null)
    {
        $this->enforceVersion = false;

        $version = new ChildDiaporamaVersion();
        $version->setId($this->getId());
        $version->setShortcode($this->getShortcode());
        $version->setCreatedAt($this->getCreatedAt());
        $version->setUpdatedAt($this->getUpdatedAt());
        $version->setVersion($this->getVersion());
        $version->setVersionCreatedAt($this->getVersionCreatedAt());
        $version->setVersionCreatedBy($this->getVersionCreatedBy());
        $version->setDiaporama($this);
        $version->save($con);

        return $version;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con The connection to use
     *
     * @return  ChildDiaporama The current object (for fluent API support)
     */
    public function toVersion($versionNumber, $con = null)
    {
        $version = $this->getOneVersion($versionNumber, $con);
        if (!$version) {
            throw new PropelException(sprintf('No ChildDiaporama object found with version %d', $version));
        }
        $this->populateFromVersion($version, $con);

        return $this;
    }

    /**
     * Sets the properties of the current object to the value they had at a specific version
     *
     * @param ChildDiaporamaVersion $version The version object to use
     * @param ConnectionInterface   $con the connection to use
     * @param array                 $loadedObjects objects that been loaded in a chain of populateFromVersion calls on referrer or fk objects.
     *
     * @return ChildDiaporama The current object (for fluent API support)
     */
    public function populateFromVersion($version, $con = null, &$loadedObjects = array())
    {
        $loadedObjects['ChildDiaporama'][$version->getId()][$version->getVersion()] = $this;
        $this->setId($version->getId());
        $this->setShortcode($version->getShortcode());
        $this->setCreatedAt($version->getCreatedAt());
        $this->setUpdatedAt($version->getUpdatedAt());
        $this->setVersion($version->getVersion());
        $this->setVersionCreatedAt($version->getVersionCreatedAt());
        $this->setVersionCreatedBy($version->getVersionCreatedBy());

        return $this;
    }

    /**
     * Gets the latest persisted version number for the current object
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  integer
     */
    public function getLastVersionNumber($con = null)
    {
        $v = ChildDiaporamaVersionQuery::create()
            ->filterByDiaporama($this)
            ->orderByVersion('desc')
            ->findOne($con);
        if (!$v) {
            return 0;
        }

        return $v->getVersion();
    }

    /**
     * Checks whether the current object is the latest one
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  Boolean
     */
    public function isLastVersion($con = null)
    {
        return $this->getLastVersionNumber($con) == $this->getVersion();
    }

    /**
     * Retrieves a version object for this entity and a version number
     *
     * @param   integer $versionNumber The version number to read
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ChildDiaporamaVersion A version object
     */
    public function getOneVersion($versionNumber, $con = null)
    {
        return ChildDiaporamaVersionQuery::create()
            ->filterByDiaporama($this)
            ->filterByVersion($versionNumber)
            ->findOne($con);
    }

    /**
     * Gets all the versions of this object, in incremental order
     *
     * @param   ConnectionInterface $con the connection to use
     *
     * @return  ObjectCollection A list of ChildDiaporamaVersion objects
     */
    public function getAllVersions($con = null)
    {
        $criteria = new Criteria();
        $criteria->addAscendingOrderByColumn(DiaporamaVersionTableMap::VERSION);

        return $this->getDiaporamaVersions($criteria, $con);
    }

    /**
     * Compares the current object with another of its version.
     * <code>
     * print_r($book->compareVersion(1));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer             $versionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersion($versionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->toArray();
        $toVersion = $this->getOneVersion($versionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Compares two versions of the current object.
     * <code>
     * print_r($book->compareVersions(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   integer             $fromVersionNumber
     * @param   integer             $toVersionNumber
     * @param   string              $keys Main key used for the result diff (versions|columns)
     * @param   ConnectionInterface $con the connection to use
     * @param   array               $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    public function compareVersions($fromVersionNumber, $toVersionNumber, $keys = 'columns', $con = null, $ignoredColumns = array())
    {
        $fromVersion = $this->getOneVersion($fromVersionNumber, $con)->toArray();
        $toVersion = $this->getOneVersion($toVersionNumber, $con)->toArray();

        return $this->computeDiff($fromVersion, $toVersion, $keys, $ignoredColumns);
    }

    /**
     * Computes the diff between two versions.
     * <code>
     * print_r($book->computeDiff(1, 2));
     * => array(
     *   '1' => array('Title' => 'Book title at version 1'),
     *   '2' => array('Title' => 'Book title at version 2')
     * );
     * </code>
     *
     * @param   array     $fromVersion     An array representing the original version.
     * @param   array     $toVersion       An array representing the destination version.
     * @param   string    $keys            Main key used for the result diff (versions|columns).
     * @param   array     $ignoredColumns  The columns to exclude from the diff.
     *
     * @return  array A list of differences
     */
    protected function computeDiff($fromVersion, $toVersion, $keys = 'columns', $ignoredColumns = array())
    {
        $fromVersionNumber = $fromVersion['Version'];
        $toVersionNumber = $toVersion['Version'];
        $ignoredColumns = array_merge(array(
            'Version',
            'VersionCreatedAt',
            'VersionCreatedBy',
        ), $ignoredColumns);
        $diff = array();
        foreach ($fromVersion as $key => $value) {
            if (in_array($key, $ignoredColumns)) {
                continue;
            }
            if ($toVersion[$key] != $value) {
                switch ($keys) {
                    case 'versions':
                        $diff[$fromVersionNumber][$key] = $value;
                        $diff[$toVersionNumber][$key] = $toVersion[$key];
                        break;
                    default:
                        $diff[$key] = array(
                            $fromVersionNumber => $value,
                            $toVersionNumber => $toVersion[$key],
                        );
                        break;
                }
            }
        }

        return $diff;
    }
    /**
     * retrieve the last $number versions.
     *
     * @param Integer $number the number of record to return.
     * @return PropelCollection|array \Diaporamas\Model\DiaporamaVersion[] List of \Diaporamas\Model\DiaporamaVersion objects
     */
    public function getLastVersions($number = 10, $criteria = null, $con = null)
    {
        $criteria = ChildDiaporamaVersionQuery::create(null, $criteria);
        $criteria->addDescendingOrderByColumn(DiaporamaVersionTableMap::VERSION);
        $criteria->limit($number);

        return $this->getDiaporamaVersions($criteria, $con);
    }
    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
