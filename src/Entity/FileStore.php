<?php

namespace App\Entity;

use App\Base\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @ORM\Entity
 * @ORM\Table(name="file_store")
 * @ORM\HasLifecycleCallbacks
 */
class FileStore extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    public $id;

    /**
     * @ORM\Column(type="integer")
     */
    public $filesize = 0;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $mime_type;

    /**
     * @ORM\Column(type="blob")
     */
    public $data;

    /**
     * @ORM\OneToMany(targetEntity="FileLink", mappedBy="filestore", indexBy="id", orphanRemoval=true, cascade={"persist","remove"}, fetch="EXTRA_LAZY")
     * @var ArrayCollection|FileLink
     */
    public $links = null;

    /**
     * Temporary uploaded file
     * @var UploadedFile
     */
    protected $uploaded_file;

    public function getProject(): ?Project
    {
        return null;
    }

    public static function getValidationRules(): ?array
    {
        return null;
    }
}