<?php

namespace App\Entity;

use App\Base\Entity;
use App\Entity\RunItem;
use App\Service\NavigationFactory;
use App\Validation\Rules;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ORM\Entity
 * @ORM\Table(name="file_links")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="parent_type", type="string")
 * @ORM\DiscriminatorMap({"item" = "Attachment", "activity" = "ActivityFile", "runitem" = "RunItemFile"})
 * @ORM\HasLifecycleCallbacks
 */
abstract class FileLink extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=Ramsey\Uuid\Doctrine\UuidGenerator::class)
     */
    public $id;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $filename;

    /**
     * @ORM\Column(type="string", nullable=true, length=100)
     */
    public $classifier;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\ManyToOne(targetEntity="FileStore", inversedBy="links", cascade={"persist"})
     * @ORM\JoinColumn(name="filestore_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @var FileStore
     */
    public $filestore;

    /**
     * @ORM\ManyToOne(targetEntity="Item")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @ORM\ManyToOne(targetEntity="Activity")
     * @ORM\JoinColumn(name="activity_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * @var Activity
     */
    public $activity;

    /**
     * @ORM\ManyToOne(targetEntity="RunItem")
     * @ORM\JoinColumn(name="runitem_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * @var RunItem
     */
    public $runitem;

    public function cascadeDelete(): array
    {
        $cascade = parent::cascadeDelete();
        if ($this->filestore->links->count() == 1) {
            $cascade[] = $this->filestore;
        }
        return $cascade;
    }

    abstract public function downloadUrl(NavigationFactory $nav);

    public function getHtmlLink(NavigationFactory $nav)
    {
        $base = log($this->filestore->filesize) / log(1024);
        $suffix = [" bytes", " KB", " MB", " GB", " TB"][floor($base)];
        $humansize = round(pow(1024, $base - floor($base)), 2).$suffix;
        return '<a href="'.htmlentities($this->downloadUrl($nav)).'" title="'.$humansize.' '.$this->filestore->mime_type.'">'.htmlentities($this->filename).'</a>';
    }

    public function __toString()
    {
        return $this->filename;
    }

    public function returnDownload()
    {
        return new Response(stream_get_contents($this->filestore->data), 200, [
            'Content-type' => $this->filestore->mime_type,
            'Content-Disposition' => "inline; filename=\"{$this->filename}\"",
        ]);
    }

    public static function getValidationRules(): ?array
    {
        return [
            'filename' => Rules::new()->trim()->required()->truncate(100),
            'classifier' => Rules::new()->trim()->required()->truncate(100),
            'description' => Rules::new()->trim(),
        ];
    }
}