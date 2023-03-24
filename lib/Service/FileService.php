<?php

/**
 * Nextcloud - Welcome
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 *
 * Addition of multilingual support by Joaquim Homrighausen
 * 2023-03, github @joho1968
 */

namespace OCA\Welcome\Service;

use Exception;
use OC\Files\Node\File;
use OC\User\NoUserException;
use OCA\Welcome\AppInfo\Application;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

function startsWith(string $haystack, string $needle): bool {
	$length = mb_strlen($needle);
	return (mb_substr($haystack, 0, $length) === $needle);
}

class FileService {

	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct (IRootFolder $root,
								IConfig $config,
								IURLGenerator $urlGenerator,
								LoggerInterface $logger,
								IUserManager $userManager) {
		$this->root = $root;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	/**
	 * @return File|null
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getWidgetFile(): ?File {
		$filePath = $this->config->getAppValue(Application::APP_ID, 'filePath');
		$userName = $this->config->getAppValue(Application::APP_ID, 'userName');
		$userId = $this->config->getAppValue(Application::APP_ID, 'userId');

        // Get current user so we can get settings
        $sessionUserId = \OC_User::getUser();
        // Figure out system's default language, default to "en"
        $systemLang = $this->config->getSystemValue('default_language', 'en');
        if (empty($systemLang)) {
            // We may need to override this manually because it's apparently
            // possible to get an empty string here
            $systemLang = 'en';
        }
        // Figure out if system is configured to force a language
        $systemForceLang = $this->config->getSystemValue('force_language', false);
        // Figure out system's default locale, default to "en_US"
        $systemLocale = $this->config->getSystemValue('default_locale', 'en_US');
        if (empty($systemLocale)) {
            // We may need to override this manually because it's apparently
            // possible to get an empty string here
            $systemLocale = 'en_US';
        }
        // Figure out if system is configured to force a locale
        $systemForceLocale = $this->config->getSystemValue('force_locale', false);
        //Finally, fetch user's lang and locale settings
        $userLang = $this->config->getUserValue($sessionUserId, 'core', 'lang', 'en');
        $userLocale = $this->config->getUserValue($sessionUserId, 'core', 'locale', 'en_US');
        // Apply defaults
        if ($systemForceLang !== false && $systemForceLang !== 'false') {
            if ($systemForceLang !== true && $systemForceLang !== 'true') {
                // 'force_language' is set to an actual language code
                $userLang = $systemForceLang;
            } else {
                // 'force_language' is set to true
                $userLang = $systemLang;
            }
        } elseif (empty($userLang)) {
            $userLang = 'en';
        }
        if ($systemForceLocale !== false && $systemForceLocale !== 'false') {
            if ($systemForceLocale !== true && $systemForceLocale !== 'true') {
                // 'force_locale' is set to an actual language code. This could
                // possibly not be allowed, but we'll make allowance for it :-)
                $userLocale = $systemForceLocale;
            } else {
                // 'force_locale' is set to true
                $userLocale = $systemLocale;
            }
        } elseif (empty($userLocal)) {
            $userLocale = 'en_US';
        }

        /**
         * Note: For some languagues, and German in particular, Nextcloud uses
         * the full locale for the language setting. This is, apparently, for
         * historical reasons (ownCloud) For example, informal German has the
         * lang set to "de", whereas formal German has the lang set to "de_DE".
         * This will result in a different filename, although they're both
         * technically german. So it'd be "welcome_de.md" and
         * "welcome.de_DE.md" in that case. But we'll split the final language
         * string at _ (underscore) to avoid this.
         */
        $tmpSplit = explode('_', $userLang, 2);
        if (is_array($tmpSplit)) {
            // We'll assume string is reasonably correct and just use the
            // first part. This would not handle a situation where the string
            // looks like "_de_DE", but that's an "invalid" string anyway.
            $userLang = strtolower($tmpSplit[0]);
        }

		if ($filePath && $userName && $userId && $this->userManager->userExists($userId)) {
			$userFolder = $this->root->getUserFolder($userId);
            $fileBase = dirname($filePath) . basename($filePath, '.md');
            $userBase = $fileBase . '_' . $userLang . '.md';
            // First attempt to locate language specific welcome_xx.md file
			if ($userFolder->nodeExists($userBase)) {
				$file = $userFolder->get($userBase);
				if ($file instanceof File) {
					return $file;
				}
            }
            // No language specific file found, use whatever is configured
			if ($userFolder->nodeExists($filePath)) {
				$file = $userFolder->get($filePath);
				if ($file instanceof File) {
					return $file;
				}
			}
		}
		return null;
	}

	/**
	 * @return array|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function getWidgetContent(): ?array {
		$this->getWidgetHttpImageUrls();

		$userName = $this->config->getAppValue(Application::APP_ID, 'userName', '');
		$userId = $this->config->getAppValue(Application::APP_ID, 'userId', '');
		$supportUserName = $this->config->getAppValue(Application::APP_ID, 'supportUserName', '');
		$supportUserId = $this->config->getAppValue(Application::APP_ID, 'supportUserId', '');
		$supportText = $this->config->getAppValue(Application::APP_ID, 'supportText', '');

		$file = $this->getWidgetFile();
		$content = $file->getContent();
		if ($content !== null) {
			$content = $this->replaceImagePaths($content, $file->getParent());
			// prepend a new line to avoid having the first line interpreted as code...
			return [
				'content' => "\n" . trim($content),
				'userId' => $userId,
				'userName' => $userName,
				'supportUserId' => $supportUserId,
				'supportUserName' => $supportUserName,
				'supportText' => $supportText,
			];
		}
		return null;
	}

	/**
	 * @param int $fileId
	 * @return File|null
	 * @throws InvalidPathException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getImage(int $fileId): ?File {
		$widgetFile = $this->getWidgetFile();
		$parent = $widgetFile->getParent();
		$attachmentFolderName = '.attachments.' . $widgetFile->getId();
		if ($parent->nodeExists($attachmentFolderName)) {
			$attachmentFolder = $parent->get($attachmentFolderName);
			if ($attachmentFolder instanceof Folder) {
				$attachment = $attachmentFolder->getById($fileId);
				if (is_array($attachment) && !empty($attachment)) {
					$candidate = $attachment[0];
					if ($candidate instanceof File) {
						return $candidate;
					}
				}
			}
		}
		return null;
	}

	/**
	 * @param string $content
	 * @param Folder $folder
	 * @return string
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	private function replaceImagePaths(string $content, Folder $folder): string {
		preg_match_all(
			'/\!\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[\])*\])*\])*\])*\])*\])*\]\(([^)&]+)\)/',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		foreach ($matches as $match) {
			$path = $match[1];
			$decodedPath = urldecode($path);
			if (!startsWith($path, 'http://') && !startsWith($path, 'https://') && $folder->nodeExists($decodedPath)) {
				$file = $folder->get($decodedPath);
				if ($file instanceof File) {
					$fullMatch = $match[0];
					$welcomeImageUrl = $this->urlGenerator->linkToRoute(Application::APP_ID . '.config.getWidgetImage', ['fileId' => $file->getId()]);
					$newLink = str_replace($path, $welcomeImageUrl, $fullMatch);
					$content = str_replace($fullMatch, $newLink, $content);
				}
			}
		}
		return $content;
	}

	public function getWidgetHttpImageUrls(): ?array {
		try {
			$file = $this->getWidgetFile();
			if ($file !== null) {
				$content = $file->getContent();

				preg_match_all(
					'/\!\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[(?>[^\[\]]+|\[\])*\])*\])*\])*\])*\])*\]\((https?:\/\/[^)&]+)\)/',
					$content,
					$matches,
					PREG_SET_ORDER
				);

				if ($matches === null) {
					return null;
				}

				return array_map(static function (array $match) {
					return urldecode($match[1]);
				}, $matches);
			}
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Failed to get widget http image URLs', ['app' => Application::APP_ID, 'exception' => $e]);
		}
		return null;
	}
}
