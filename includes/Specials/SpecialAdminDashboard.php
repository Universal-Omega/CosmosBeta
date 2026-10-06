<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Specials;

use MediaWiki\Html\TemplateParser;
use MediaWiki\Message\Message;
use MediaWiki\Skin\Cosmos\AdminDashboard\AdminDashboardStats;
use MediaWiki\Skin\Cosmos\AdminDashboard\AdvancedSectionBuilder;
use MediaWiki\Skin\Cosmos\AdminDashboard\DashboardControl;
use MediaWiki\Skin\Cosmos\AdminDashboard\DashboardControlRegistry;
use MediaWiki\Skin\Cosmos\AdminDashboard\DashboardSection;
use MediaWiki\Skin\Cosmos\AdminDashboard\DashboardStats;
use MediaWiki\Skin\Cosmos\AdminDashboard\DashboardTab;
use MediaWiki\SpecialPage\SpecialPage;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;

class SpecialAdminDashboard extends SpecialPage {

	public function __construct(
		private readonly AdminDashboardStats $stats,
		private readonly AdvancedSectionBuilder $advancedSectionBuilder,
		private readonly DashboardControlRegistry $controlRegistry,
		private readonly TemplateParser $templateParser,
	) {
		parent::__construct( 'AdminDashboard' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'cosmosbeta-admindashboard';
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'cosmosbeta-admindashboard' );
	}

	/** @inheritDoc */
	public function getAssociatedNavigationLinks(): array {
		return array_map(
			fn ( DashboardTab $tab ): string => $this->getPageTitle( $tab->value )->getPrefixedText(),
			DashboardTab::cases()
		);
	}

	/** @inheritDoc */
	public function getShortDescription( string $path = '' ): string {
		$tab = DashboardTab::fromSubPage( $path );
		return $tab ? $this->msg( $tab->getMessageKey() )->text() : '';
	}

	/** @inheritDoc */
	protected function getSubpagesForPrefixSearch(): array {
		return array_map( static fn ( DashboardTab $tab ): string => $tab->value, DashboardTab::cases() );
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->checkPermissions();
		$this->addHelpLink( 'Skin:Cosmos' );

		$tab = DashboardTab::fromSubPage( $subPage );
		$out = $this->getOutput();

		if ( $tab === null ) {
			$out->redirect( $this->getPageTitle( DashboardTab::General->value )->getFullURL() );
			return;
		}

		$out->addModuleStyles( [
			'skins.cosmosbeta.admindashboard.codex',
			'skins.cosmosbeta.admindashboard',
		] );

		$out->addHTML( $this->templateParser->processTemplate( 'AdminDashboard', $this->getTemplateData( $tab ) ) );
	}

	private function getTemplateData( DashboardTab $tab ): array {
		$controls = $this->controlRegistry->getControls( $this->getUser(), $this );

		return [
			'is-general' => $tab === DashboardTab::General,
			'is-advanced' => $tab === DashboardTab::Advanced,
			'data-stats' => $this->getStatsData( $this->stats->getStats() ),
		] + match ( $tab ) {
			DashboardTab::General => [ 'array-sections' => $this->getSections( $controls ) ],
			DashboardTab::Advanced => [ 'array-groups' => $this->getGroups( $controls ) ],
		};
	}

	/**
	 * @param DashboardControl[] $controls
	 */
	private function getSections( array $controls ): array {
		$sections = [];
		foreach ( DashboardSection::cases() as $section ) {
			$items = array_filter( $controls, static fn ( DashboardControl $control ): bool => $control->section === $section );
			if ( !$items ) {
				continue;
			}

			$sections[] = [
				'id' => $section->value,
				'title' => $this->msg( $section->getMessageKey() )->text(),
				'array-controls' => array_values( array_map( $this->getControlData( ... ), $items ) ),
			];
		}

		return $sections;
	}

	private function getControlData( DashboardControl $control ): array {
		return [
			'id' => $control->id,
			'url' => $control->url,
			'label' => $this->msg( $control->getLabelKey() )->text(),
			'description' => $this->msg( $control->getDescriptionKey() )->text(),
			'is-dialog' => $control->opensCreateDialog,
			'is-external' => $control->isExternal,
		];
	}

	/**
	 * @param DashboardControl[] $controls
	 */
	private function getGroups( array $controls ): array {
		$excluded = array_values( array_filter( array_map(
			static fn ( DashboardControl $control ): ?string => $control->specialPage,
			$controls
		) ) );

		$groups = [];
		foreach ( $this->advancedSectionBuilder->build( $this->getContext(), $excluded ) as $group => $pages ) {
			$message = $this->msg( "specialpages-group-$group" );
			$groups[] = [
				'id' => $group,
				'title' => $message->exists() ? $message->text() : $group,
				'array-links' => array_map(
					static fn ( array $page ): array => [
						'url' => $page['url'],
						'text' => $page['text'],
						'is-restricted' => $page['isRestricted'],
					],
					$pages
				),
			];
		}

		return $groups;
	}

	private function getStatsData( DashboardStats $stats ): array {
		$language = $this->getLanguage();
		$format = $this->msg( 'cosmosbeta-admindashboard-stats-date-format' )->plain();
		$number = static fn ( int $value ): string => $language->formatNum( $value );

		$totals = [
			'articles' => $stats->articles,
			'pages' => $stats->pages,
			'edits' => $stats->edits,
			'files' => $stats->files,
			'users' => $stats->users,
			'activeusers' => $stats->activeUsers,
		];

		return [
			'msg-stats-title' => $this->msg( 'cosmosbeta-admindashboard-stats-title' )->text(),
			'msg-activity-title' => $this->msg( 'cosmosbeta-admindashboard-stats-activity' )->text(),
			'msg-date' => $this->msg( 'cosmosbeta-admindashboard-stats-date' )->text(),
			'msg-edits' => $this->msg( 'cosmosbeta-admindashboard-stats-edits' )->text(),
			'msg-pages' => $this->msg( 'cosmosbeta-admindashboard-stats-pages' )->text(),
			'msg-uploads' => $this->msg( 'cosmosbeta-admindashboard-stats-uploads' )->text(),
			'msg-week' => $this->msg( 'cosmosbeta-admindashboard-stats-week' )->text(),
			'array-totals' => array_values( array_map(
				fn ( string $id, int $value ): array => [
					'id' => $id,
					'label' => $this->msg( "cosmosbeta-admindashboard-stats-total-$id" )->text(),
					'value' => $number( $value ),
				],
				array_keys( $totals ),
				$totals
			) ),
			'array-days' => array_map(
				static fn ( $day ): array => [
					'date' => $language->sprintfDate( $format, $day->day . '000000' ),
					'edits' => $number( $day->edits ),
					'pages' => $number( $day->newPages ),
					'uploads' => $number( $day->uploads ),
				],
				$stats->days
			),
			'week-edits' => $number( $stats->getWeeklyEdits() ),
			'week-pages' => $number( $stats->getWeeklyNewPages() ),
			'week-uploads' => $number( $stats->getWeeklyUploads() ),
			'has-days' => count( $stats->days ) > 0,
		];
	}
}
